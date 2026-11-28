<?php

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Support\ChatSummaryQuery;
use App\Support\KnowledgeCorpus;
use App\Support\RetrievalFacets;
use App\Support\RetrievalTopics;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class RetrievalService
{
    private const string LEXICAL_DOCUMENT = "to_tsvector('english', concat_ws(' ', knowledge_articles.title, coalesce(knowledge_chunks.heading, ''), knowledge_chunks.body))";

    /**
     * @return Collection<int, array{chunk: KnowledgeChunk, similarity: float}>
     */
    public function search(string $query, KnowledgeCorpus $corpus, int $limit = 6, float $minSimilarity = 0.45): Collection
    {
        $limit = max(1, $limit);
        $candidates = max($limit, (int) config('supportflow.retrieval.candidates', 12));
        $maxPerArticle = max(1, (int) config('supportflow.retrieval.max_per_article', 2));
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $overview = $this->overviewArticles($query, $corpus);

        if ($overview !== null) {
            return $this->overviewPassages($overview);
        }

        $topics = RetrievalTopics::matching($query);
        $facets = RetrievalFacets::queries($query);
        $dedicated = [...(count($topics) >= 2 ? array_slice($topics, 0, 3) : []), ...$facets];
        $embedTexts = [$query, ...array_column($dedicated, 'embed')];
        $embeddings = $this->embeddingsFor($embedTexts);
        $rankInputs = [];

        $fullEmbedding = $embeddings[$query] ?? [];

        if ($fullEmbedding !== []) {
            $rankInputs[] = ['embedding' => $fullEmbedding];
        }

        $rankInputs[] = ['lexical' => $this->neutralizeWebsearchOperators($query)];
        $orQuery = $this->orExpandedQuery($query);
        if ($orQuery !== null && $orQuery !== $query) {
            $rankInputs[] = ['lexical' => $orQuery];
        }

        foreach ($dedicated as $input) {
            $embedding = $embeddings[$input['embed']] ?? [];
            if ($embedding === []) {
                $embedding = $this->embed($input['embed']);
            }

            if ($embedding !== []) {
                $rankInputs[] = ['embedding' => $embedding];
            }
            $rankInputs[] = ['lexical' => $input['lexical']];
        }

        [$rankLists, $similarities] = $this->candidateRanks($rankInputs, $candidates, $minSimilarity, $corpus);

        if ($rankLists === []) {
            return collect();
        }

        $orderedIds = array_map(intval(...), array_keys($this->reciprocalRankFusion($rankLists)));
        $chunks = KnowledgeChunk::query()
            ->with('article')
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        return $this->selectDiverse($orderedIds, $chunks, $topics, $similarities, $limit, $maxPerArticle, $minSimilarity, $query, $corpus);
    }

    /** @return Collection<int, KnowledgeArticle>|null */
    private function overviewArticles(string $query, KnowledgeCorpus $corpus): ?Collection
    {
        $target = ChatSummaryQuery::target($query);

        if ($target === null) {
            return null;
        }

        $articles = KnowledgeArticle::query()->tap(fn ($query) => $corpus->apply($query));

        if ($target !== '' && $target !== '*') {
            $title = preg_replace('/\.(?:pdf|docx|txt|md|markdown)$/u', '', $target) ?? $target;
            $selected = (clone $articles)->where(function ($query) use ($title, $target): void {
                $query->whereRaw('lower(title) = ?', [$title])
                    ->orWhereHas('document', fn ($document) => $document->whereRaw('lower(original_name) = ?', [$target]));
            })->orderBy('id')->get(['id']);

            if ($selected->isNotEmpty() || preg_match('/\.(?:pdf|docx|txt|md|markdown)$/u', $target) === 1) {
                return $selected;
            }

            return null;
        }

        return $articles->orderBy('id')->get(['id']);
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $articles
     * @return Collection<int, array{chunk: KnowledgeChunk, similarity: float}>
     */
    private function overviewPassages(Collection $articles): Collection
    {
        $groups = KnowledgeChunk::query()->whereIn('knowledge_article_id', $articles->pluck('id')->all())
            ->where('body', '!=', '')->orderBy('knowledge_article_id')->orderBy('id')
            ->get(['id', 'knowledge_article_id'])->groupBy('knowledge_article_id')->values();
        $ids = [];
        $budget = 24;

        foreach ($groups as $index => $chunks) {
            $quota = min($chunks->count(), (int) ceil($budget / ($groups->count() - $index)));

            for ($position = 0; $position < $quota; $position++) {
                $offset = $quota === 1 ? 0 : (int) round($position * ($chunks->count() - 1) / ($quota - 1));
                $ids[] = $chunks[$offset]->id;
            }

            $budget -= $quota;
        }

        return KnowledgeChunk::query()->with('article')->whereIn('id', $ids)->orderBy('knowledge_article_id')->orderBy('id')
            ->get()->map($this->overviewMatch(...));
    }

    /** @return array{chunk: KnowledgeChunk, similarity: float} */
    private function overviewMatch(KnowledgeChunk $chunk): array
    {
        return ['chunk' => $chunk, 'similarity' => 1.0];
    }

    /** @param Collection<int, array{chunk: KnowledgeChunk, similarity: float}> $matches */
    public function overviewIsPartial(string $query, KnowledgeCorpus $corpus, Collection $matches): bool
    {
        $articles = $this->overviewArticles($query, $corpus);

        return $articles !== null && KnowledgeChunk::query()->whereIn('knowledge_article_id', $articles->pluck('id')->all())
            ->where('body', '!=', '')->count() > $matches->count();
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0] ?? [];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Embeddings::for($texts)
            ->dimensions((int) config('supportflow.embeddings.dimensions'))
            ->generate(Lab::OpenAI, (string) config('supportflow.models.embeddings'));

        /** @var list<list<float>> $vectors */
        $vectors = $response->embeddings;

        return $vectors;
    }

    /**
     * Keep every search's ordered LIMIT inside its subquery, including pgvector's nearest-neighbor ordering.
     *
     * @param  list<array{embedding: list<float>}|array{lexical: string}>  $inputs
     * @return array{list<array<int, int>>, array<int, float>}
     */
    private function candidateRanks(array $inputs, int $candidates, float $minSimilarity, KnowledgeCorpus $corpus): array
    {
        $batch = (new KnowledgeChunk)->getConnection()->query();
        $columns = [];
        $lexicalColumns = [];
        foreach ($inputs as $index => $input) {
            $vector = isset($input['embedding']);
            $ranked = KnowledgeChunk::query()->select('knowledge_chunks.id');

            if ($vector) {
                $ranked->whereHas('article', fn ($query) => $corpus->apply($query))
                    ->whereNotNull('embedding')
                    ->selectVectorDistance('embedding', $input['embedding'], as: 'distance')
                    ->whereVectorSimilarTo('embedding', $input['embedding'], minSimilarity: $minSimilarity);
                $aggregate = "coalesce(json_agg(json_build_array(ranked.id, ranked.distance) order by ranked.distance), '[]'::json)";
            } else {
                $query = trim($input['lexical']);
                if ($query === '') {
                    continue;
                }

                if (isset($lexicalColumns[$query])) {
                    $columns[] = [$lexicalColumns[$query], false];

                    continue;
                }

                $ranked->join('knowledge_articles', 'knowledge_articles.id', '=', 'knowledge_chunks.knowledge_article_id')
                    ->tap(fn ($builder) => $corpus->apply($builder))
                    ->whereRaw(self::LEXICAL_DOCUMENT.' @@ websearch_to_tsquery(\'english\', ?)', [$query])
                    ->selectRaw('ts_rank('.self::LEXICAL_DOCUMENT.', websearch_to_tsquery(\'english\', ?)) as lexical_rank', [$query])
                    ->orderByDesc('lexical_rank');
                $aggregate = "coalesce(json_agg(json_build_array(ranked.id, null) order by ranked.lexical_rank desc), '[]'::json)";
            }

            $column = 'candidate_ranks_'.$index;
            $columns[] = [$column, $vector];
            if (! $vector) {
                $lexicalColumns[$query] = $column;
            }
            $ids = $batch->newQuery()
                ->fromSub($ranked->limit($candidates)->toBase(), 'ranked')
                ->selectRaw($aggregate);
            $batch->selectSub($ids, $column);
        }

        if ($columns === []) {
            return [[], []];
        }

        $row = $batch->first();
        $rankLists = [];
        $similarities = [];
        foreach ($columns as [$column, $vector]) {
            $ranks = [];
            /** @var list<array{int, int|float|null}> $hits */
            $hits = json_decode((string) $row->{$column}, true, flags: JSON_THROW_ON_ERROR);
            foreach ($hits as $position => [$id, $distance]) {
                $ranks[$id] = $position + 1;
                if ($vector) {
                    $similarity = $distance === null ? $minSimilarity : max(0, 1 - $distance);
                    $similarities[$id] = max($similarities[$id] ?? 0.0, round($similarity, 4));
                }
            }
            if ($ranks !== []) {
                $rankLists[] = $ranks;
            }
        }

        return [$rankLists, $similarities];
    }

    /**
     * @param  list<array<int, int>>  $rankLists
     * @return array<int, float>
     */
    protected function reciprocalRankFusion(array $rankLists, int $k = 60): array
    {
        $scores = [];

        foreach ($rankLists as $ranks) {
            foreach ($ranks as $id => $rank) {
                $scores[$id] = ($scores[$id] ?? 0.0) + (1.0 / ($k + $rank));
            }
        }

        arsort($scores);

        return $scores;
    }

    protected function orExpandedQuery(string $query): ?string
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9-]{2,}/', $query, $matches);

        $stop = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'your', 'can', 'how', 'what', 'when', 'with', 'without', 'this', 'that', 'from', 'have', 'has', 'complete', 'harbor', 'outfitters', 'order', 'orders', 'demo', 'sales', 'please', 'thanks'];
        $terms = [];

        foreach ($matches[0] as $term) {
            $term = mb_strtolower($term);
            if (in_array($term, $stop, true) || in_array($term, $terms, true)) {
                continue;
            }
            $terms[] = $term;
        }

        if (count($terms) < 2) {
            return null;
        }

        $head = array_slice($terms, 0, 8);
        $tail = array_slice($terms, -8);
        $mixed = array_values(array_unique([...$head, ...$tail]));

        return implode(' OR ', $mixed);
    }

    /**
     * Neutralize websearch AND/OR/NOT operators in untrusted visitor text.
     * Do not use this on queries we generate with explicit OR expansion.
     */
    protected function neutralizeWebsearchOperators(string $query): string
    {
        $neutral = preg_replace('/\b(?:and|or|not)\b/i', ' ', $query) ?? $query;

        return trim(preg_replace('/\s+/', ' ', $neutral) ?? $neutral);
    }

    /**
     * @param  list<string>  $texts
     * @return array<string, list<float>>
     */
    private function embeddingsFor(array $texts): array
    {
        $unique = array_values(array_unique(array_filter(
            $texts,
            static fn (string $text): bool => trim($text) !== '',
        )));

        if ($unique === []) {
            return [];
        }

        $vectors = $this->embedMany($unique);
        $mapped = [];

        foreach ($unique as $index => $text) {
            $vector = $vectors[$index] ?? [];
            $mapped[$text] = is_array($vector) ? $vector : [];
        }

        return $mapped;
    }

    /**
     * @param  list<int>  $orderedIds
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @param  list<array{key: string, embed: string, lexical: string}>  $topics
     * @param  array<int, float>  $similarities
     * @return Collection<int, array{chunk: KnowledgeChunk, similarity: float}>
     */
    protected function selectDiverse(
        array $orderedIds,
        Collection $chunks,
        array $topics,
        array $similarities,
        int $limit,
        int $maxPerArticle,
        float $minSimilarity,
        string $query,
        KnowledgeCorpus $corpus,
    ): Collection {
        $selected = [];
        $perArticle = [];
        $facets = RetrievalFacets::matching($query);

        $articleCap = function (int $articleId) use ($facets, $chunks, $maxPerArticle, $corpus, $query, $limit): int {
            $covered = 0;

            foreach ($facets as $facet) {
                foreach ($chunks as $chunk) {
                    if ((int) $chunk->knowledge_article_id !== $articleId) {
                        continue;
                    }

                    if (RetrievalFacets::chunkCovers($chunk, $facet)) {
                        $covered++;
                        break;
                    }
                }
            }

            $cap = max($maxPerArticle, min(3, $covered));

            if ($corpus->isHarbor()) {
                return $cap;
            }

            $asked = $this->askedPartWords($query);
            $headingHits = 0;

            foreach ($chunks as $chunk) {
                if ((int) $chunk->knowledge_article_id !== $articleId) {
                    continue;
                }

                if ($asked !== [] && array_any($asked, fn (array $words): bool => $this->headingCoversAskedPart($chunk, $words))) {
                    $headingHits++;
                }
            }

            return max($cap, min($limit, $headingHits));
        };

        $tryAdd = function (int $id) use (&$selected, &$perArticle, $chunks, $articleCap, $limit): bool {
            if (count($selected) >= $limit || in_array($id, $selected, true)) {
                return false;
            }

            $chunk = $chunks->get($id);
            if ($chunk === null) {
                return false;
            }

            $articleId = (int) $chunk->knowledge_article_id;
            if (($perArticle[$articleId] ?? 0) >= $articleCap($articleId)) {
                return false;
            }

            $selected[] = $id;
            $perArticle[$articleId] = ($perArticle[$articleId] ?? 0) + 1;

            return true;
        };

        foreach ($facets as $facet) {
            foreach ($orderedIds as $id) {
                $chunk = $chunks->get($id);
                if ($chunk instanceof KnowledgeChunk && RetrievalFacets::chunkCovers($chunk, $facet)) {
                    $tryAdd($id);
                    break;
                }
            }
        }

        if (count($topics) >= 2) {
            foreach ($topics as $topic) {
                foreach ($orderedIds as $id) {
                    $chunk = $chunks->get($id);
                    if ($chunk instanceof KnowledgeChunk && RetrievalTopics::chunkCovers($chunk, $topic['key'])) {
                        $tryAdd($id);
                        break;
                    }
                }
            }
        }

        foreach ($orderedIds as $id) {
            $tryAdd($id);
            if (count($selected) >= $limit) {
                break;
            }
        }

        $selected = $this->preferUniquelyCoveredAskedParts($orderedIds, $chunks, $selected, $query);

        return collect($selected)->values()->map(function (int $id) use ($chunks, $similarities, $minSimilarity): array {
            /** @var KnowledgeChunk $chunk */
            $chunk = $chunks->get($id);

            return [
                'chunk' => $chunk,
                'similarity' => round($similarities[$id] ?? $minSimilarity, 4),
            ];
        });
    }

    /**
     * When the per-article cap drops a passage, keep one whose heading covers an
     * asked part that the selected passages do not already cover.
     *
     * @param  list<int>  $orderedIds
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @param  list<int>  $selected
     * @return list<int>
     */
    protected function preferUniquelyCoveredAskedParts(array $orderedIds, Collection $chunks, array $selected, string $query): array
    {
        $parts = $this->askedPartWords($query);

        if ($parts === []) {
            return $selected;
        }

        foreach ($orderedIds as $id) {
            if (in_array($id, $selected, true)) {
                continue;
            }

            $chunk = $chunks->get($id);

            if (! $chunk instanceof KnowledgeChunk) {
                continue;
            }

            $coversMissingPart = array_any(
                $parts,
                fn (array $words): bool => $this->headingCoversAskedPart($chunk, $words) && ! $this->selectionCoversAskedPart($selected, $chunks, $words),
            );

            if (! $coversMissingPart) {
                continue;
            }

            $articleId = (int) $chunk->knowledge_article_id;

            foreach ($selected as $index => $selectedId) {
                $other = $chunks->get($selectedId);

                if (! $other instanceof KnowledgeChunk || (int) $other->knowledge_article_id !== $articleId) {
                    continue;
                }

                if ($this->chunkUniquelyCoversAskedPart($selectedId, $selected, $chunks, $parts)) {
                    continue;
                }

                $selected[$index] = $id;
                break;
            }
        }

        return $selected;
    }

    /**
     * @return list<list<string>>
     */
    protected function askedPartWords(string $query): array
    {
        $stop = [
            'explain', 'the', 'and', 'for', 'are', 'what', 'how', 'with', 'about', 'please',
            'options', 'requirements', 'rules', 'details', 'information', 'policy', 'policies',
        ];
        $clauses = preg_split('/,|\band\b/i', $query) ?: [];
        $parts = [];

        foreach ($clauses as $clause) {
            preg_match_all('/[A-Za-z][A-Za-z0-9-]{2,}/', (string) $clause, $matches);
            $words = [];

            foreach ($matches[0] as $word) {
                $word = mb_strtolower($word);

                if (in_array($word, $stop, true)) {
                    continue;
                }

                if (str_ends_with($word, 's') && strlen($word) > 4) {
                    $word = substr($word, 0, -1);
                }

                $words[] = $word;
            }

            $words = array_values(array_unique($words));

            if ($words !== []) {
                $parts[] = $words;
            }
        }

        return $parts;
    }

    /**
     * @param  list<string>  $words
     */
    protected function headingCoversAskedPart(KnowledgeChunk $chunk, array $words): bool
    {
        $heading = mb_strtolower(trim((string) $chunk->heading));

        if ($heading === '') {
            return false;
        }

        return array_any($words, fn (string $word): bool => str_contains($heading, $word)
            || (str_contains($word, '-') && str_contains(mb_strtolower($chunk->body), $word)));
    }

    /**
     * @param  list<int>  $selected
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @param  list<string>  $words
     */
    protected function selectionCoversAskedPart(array $selected, Collection $chunks, array $words): bool
    {
        foreach ($selected as $id) {
            $chunk = $chunks->get($id);

            if ($chunk instanceof KnowledgeChunk && $this->headingCoversAskedPart($chunk, $words)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $selected
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @param  list<list<string>>  $parts
     */
    protected function chunkUniquelyCoversAskedPart(int $id, array $selected, Collection $chunks, array $parts): bool
    {
        $chunk = $chunks->get($id);

        if (! $chunk instanceof KnowledgeChunk) {
            return false;
        }

        foreach ($parts as $words) {
            if (! $this->headingCoversAskedPart($chunk, $words)) {
                continue;
            }

            $shared = false;

            foreach ($selected as $peerId) {
                if ($peerId === $id) {
                    continue;
                }

                $peer = $chunks->get($peerId);

                if ($peer instanceof KnowledgeChunk && $this->headingCoversAskedPart($peer, $words)) {
                    $shared = true;
                    break;
                }
            }

            if (! $shared) {
                return true;
            }
        }

        return false;
    }
}
