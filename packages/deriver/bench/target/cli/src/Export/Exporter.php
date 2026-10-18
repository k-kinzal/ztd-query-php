<?php

declare(strict_types=1);

namespace HarborOps\Export;

use Generator;
use HarborOps\Database;
use InvalidArgumentException;

final class Exporter
{
    public function __construct(private readonly Database $database)
    {
    }
    public function rows(int $community, string $state): Generator
    {
        if (!in_array($state, ['published','quarantined','removed'], true)) {
            throw new InvalidArgumentException('Unknown publication state.');
        }
        $after = 0;
        do {
            $rows = $this->database->rows('SELECT p.id,p.external_id,p.title,p.body,p.state,p.published_at FROM posts p JOIN sources s ON s.id=p.source_id WHERE s.community_id=? AND p.state=? AND p.id>? ORDER BY p.id LIMIT 500', [$community,$state,$after]);
            foreach ($rows as $row) {
                $after = (int)$row['id'];
                yield $row;
            }
        } while (count($rows) === 500);
    }
    public function csvCell(string $value): string
    {
        // Spreadsheet clients must not interpret user content as formulas.
        return preg_match('/^(?:[\s]*[=+@\-]|[\t\r])/u', $value) === 1 ? "'".$value : $value;
    }
}
