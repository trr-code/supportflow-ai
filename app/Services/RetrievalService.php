<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
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

        $topics = RetrievalTopics::matching($query);
        $rankLists = [];
        $similarities = [];

        $fullEmbedding = $this->embed($query);

        if ($fullEmbedding !== []) {
            $vectorHits = $this->vectorSearch($fullEmbedding, $candidates, $minSimilarity, $corpus);
            $rankLists[] = $this->ranksFromChunks($vectorHits->map(fn (array $row): KnowledgeChunk => $row['chunk']));
            $similarities = $this->mergeSimilarities($similarities, $vectorHits);
        }

        $lexicalHits = $this->lexicalSearch($this->neutralizeWebsearchOperators($query), $candidates, $corpus);
        if ($lexicalHits->isNotEmpty()) {
            $rankLists[] = $this->ranksFromChunks($lexicalHits);
        }

        $orQuery = $this->orExpandedQuery($query);
        if ($orQuery !== null && $orQuery !== $query) {
            $orHits = $this->lexicalSearch($orQuery, $candidates, $corpus);
            if ($orHits->isNotEmpty()) {
                $rankLists[] = $this->ranksFromChunks($orHits);
            }
        }

        if (count($topics) >= 2) {
            foreach (array_slice($topics, 0, 3) as $topic) {
                $this->addDedicatedRankLists($topic['embed'], $topic['lexical'], $candidates, $minSimilarity, $corpus, $rankLists, $similarities);
            }
        }

        foreach (RetrievalFacets::queries($query) as $facet) {
            $this->addDedicatedRankLists($facet['embed'], $facet['lexical'], $candidates, $minSimilarity, $corpus, $rankLists, $similarities);
        }

        $rankLists = array_values(array_filter($rankLists, fn (array $ranks): bool => $ranks !== []));

        if ($rankLists === []) {
            return collect();
        }

        $orderedIds = array_map(intval(...), array_keys($this->reciprocalRankFusion($rankLists)));
        $chunks = KnowledgeChunk::query()
            ->with('article')
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        return $this->selectDiverse($orderedIds, $chunks, $topics, $similarities, $limit, $maxPerArticle, $minSimilarity, $query);
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $response = Embeddings::for([$text])
            ->dimensions((int) config('supportflow.embeddings.dimensions'))
            ->generate(Lab::OpenAI, (string) config('supportflow.models.embeddings'));

        /** @var list<float> $vector */
        $vector = $response->embeddings[0] ?? [];

        return $vector;
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
     * @param  list<float>  $embedding
     * @return Collection<int, array{chunk: KnowledgeChunk, similarity: float}>
     */
    protected function vectorSearch(array $embedding, int $candidates, float $minSimilarity, KnowledgeCorpus $corpus): Collection
    {
        $chunks = KnowledgeChunk::query()
            ->with('article')
            ->whereHas('article', fn ($query) => $corpus->apply($query))
            ->whereNotNull('embedding')
            ->select('*')
            ->selectVectorDistance('embedding', $embedding, as: 'distance')
            ->whereVectorSimilarTo('embedding', $embedding, minSimilarity: $minSimilarity)
            ->limit($candidates)
            ->get();

        return $chunks->map(function (KnowledgeChunk $chunk) use ($minSimilarity): array {
            $distance = $chunk->getAttribute('distance');
            $similarity = is_numeric($distance) ? max(0, 1 - (float) $distance) : $minSimilarity;

            return [
                'chunk' => $chunk,
                'similarity' => round($similarity, 4),
            ];
        });
    }

    /**
     * Parameterized full-text search over article title, chunk heading, and chunk body.
     * The visitor string is a PDO binding; SQL identifiers stay in this method.
     *
     * @return Collection<int, KnowledgeChunk>
     */
    protected function lexicalSearch(string $lexicalQuery, int $candidates, KnowledgeCorpus $corpus): Collection
    {
        $lexicalQuery = trim($lexicalQuery);

        if ($lexicalQuery === '') {
            return collect();
        }

        return KnowledgeChunk::query()
            ->join('knowledge_articles', 'knowledge_articles.id', '=', 'knowledge_chunks.knowledge_article_id')
            ->tap(fn ($query) => $corpus->apply($query))
            ->whereRaw(self::LEXICAL_DOCUMENT.' @@ websearch_to_tsquery(\'english\', ?)', [$lexicalQuery])
            ->select('knowledge_chunks.*')
            ->selectRaw('ts_rank('.self::LEXICAL_DOCUMENT.', websearch_to_tsquery(\'english\', ?)) as lexical_rank', [$lexicalQuery])
            ->orderByDesc('lexical_rank')
            ->limit($candidates)
            ->with('article')
            ->get();
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @return array<int, int>
     */
    protected function ranksFromChunks(Collection $chunks): array
    {
        $ranks = [];
        $position = 1;

        foreach ($chunks as $chunk) {
            $id = (int) $chunk->id;

            $ranks[$id] ??= $position;

            $position++;
        }

        return $ranks;
    }

    /**
     * @param  array<int, float>  $similarities
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $hits
     * @return array<int, float>
     */
    protected function mergeSimilarities(array $similarities, Collection $hits): array
    {
        foreach ($hits as $hit) {
            $id = (int) $hit['chunk']->id;
            $similarities[$id] = max($similarities[$id] ?? 0.0, $hit['similarity']);
        }

        return $similarities;
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

        $stop = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'your', 'can', 'how', 'what', 'when', 'with', 'without', 'this', 'that', 'from', 'have', 'has', 'complete', 'harbor', 'outfitters', 'order', 'orders', 'demo', 'please', 'thanks'];
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
     * @param  list<array<int, int>>  $rankLists
     * @param  array<int, float>  $similarities
     */
    protected function addDedicatedRankLists(
        string $embedQuery,
        string $lexicalQuery,
        int $candidates,
        float $minSimilarity,
        KnowledgeCorpus $corpus,
        array &$rankLists,
        array &$similarities,
    ): void {
        $embedding = $this->embed($embedQuery);

        if ($embedding !== []) {
            $vectorHits = $this->vectorSearch($embedding, $candidates, $minSimilarity, $corpus);

            if ($vectorHits->isNotEmpty()) {
                $rankLists[] = $this->ranksFromChunks($vectorHits->map(fn (array $row): KnowledgeChunk => $row['chunk']));
                $similarities = $this->mergeSimilarities($similarities, $vectorHits);
            }
        }

        $lexicalHits = $this->lexicalSearch($lexicalQuery, $candidates, $corpus);

        if ($lexicalHits->isNotEmpty()) {
            $rankLists[] = $this->ranksFromChunks($lexicalHits);
        }
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
    ): Collection {
        $selected = [];
        $perArticle = [];
        $facets = RetrievalFacets::matching($query);

        $articleCap = function (int $articleId) use ($facets, $chunks, $maxPerArticle): int {
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

            return max($maxPerArticle, min(3, $covered));
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

        return array_any($words, fn (string $word): bool => str_contains($heading, $word));
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
