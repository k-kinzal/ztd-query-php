<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

/**
 * Document frequencies shared by the rows of a full-text search.
 *
 * @visibility MySqlMemory
 */
final class Collection
{
    /**
     * @param list<Document> $documents Visible documents, including empty and NULL documents
     */
    public function __construct(public readonly array $documents)
    {
    }

    /**
     * @return array<string, int> Number of documents containing each indexed term
     */
    public function frequencies(): array
    {
        $frequencies = [];
        foreach ($this->documents as $document) {
            foreach ($document->frequencies() as $word => $count) {
                $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
            }
        }
        return $frequencies;
    }
}
