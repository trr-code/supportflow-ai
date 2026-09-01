<?php

namespace App\Support;

class RetrievalQuery
{
    /**
     * Build a ticket retrieval query from the customer's question.
     *
     * The description is the primary signal. The subject is prefixed only when it
     * adds distinct terms that are not already present in the description.
     */
    public static function forTicket(string $subject, string $description): string
    {
        $subject = trim($subject);
        $description = trim($description);

        if ($description === '') {
            return $subject;
        }

        if ($subject === '') {
            return $description;
        }

        $descriptionLower = mb_strtolower($description);
        $subjectWords = preg_split('/\s+/', mb_strtolower($subject)) ?: [];
        $distinct = array_filter(
            $subjectWords,
            fn (string $word): bool => mb_strlen($word) > 2 && ! str_contains($descriptionLower, $word),
        );

        if ($distinct === []) {
            return $description;
        }

        return $subject.' '.$description;
    }
}
