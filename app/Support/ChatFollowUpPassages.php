<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

final class ChatFollowUpPassages
{
    public const string CONTINUATION = 'This short follow-up continues the previous subject. Answer that subject\'s detail from the passages. Do not switch to a different subject only because it shares a word with the question.';

    /**
     * Prefer chunks the previous answer already cited, then fill with the new search.
     *
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     * @return array{0: Collection<int, array{chunk: KnowledgeChunk, similarity: float}>, 1: bool}
     */
    public static function mergePreviousCitations(
        Collection $matches,
        ChatConversation $conversation,
        KnowledgeCorpus $corpus,
        int $limit,
        bool $contextual,
    ): array {
        if (! $contextual) {
            return [$matches->values(), false];
        }

        $previous = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        if (! $previous instanceof ChatMessage) {
            return [$matches->values(), false];
        }

        /** @var list<int> $ids */
        $ids = array_values(array_filter(
            array_map(intval(...), $previous->cited_chunk_ids ?? []),
            fn (int $id): bool => $id > 0,
        ));

        if ($ids === []) {
            return [$matches->values(), false];
        }

        $cited = KnowledgeChunk::query()
            ->with('article')
            ->whereIn('id', $ids)
            ->whereHas('article', function ($query) use ($corpus): void {
                $corpus->apply($query);
            })
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($ids as $id) {
            $chunk = $cited->get($id);

            if (! $chunk instanceof KnowledgeChunk) {
                continue;
            }

            $fromSearch = $matches->first(
                fn (array $row): bool => $row['chunk']->id === $chunk->id,
            );

            $rows[] = is_array($fromSearch)
                ? $fromSearch
                : [
                    'chunk' => $chunk,
                    'similarity' => (float) config('supportflow.retrieval.min_similarity'),
                ];
        }

        if ($rows === []) {
            return [$matches->values(), false];
        }

        $seen = array_map(fn (array $row): int => $row['chunk']->id, $rows);

        foreach ($matches as $row) {
            if (! in_array($row['chunk']->id, $seen, true)) {
                $rows[] = $row;
                $seen[] = $row['chunk']->id;
            }
        }

        return [collect($rows)->take(max(1, $limit))->values(), true];
    }
}
