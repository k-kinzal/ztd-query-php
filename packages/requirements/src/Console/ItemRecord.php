<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Model\Item;

/**
 * Describes an item as the complete traceability record of the spec report.
 */
final class ItemRecord
{
    /**
     * Describes an item.
     *
     * @param Item $item The item
     *
     * @return array<string, mixed> Its statement, disposition, links, design, metadata and test references
     */
    public static function describe(Item $item): array
    {
        return ['id' => $item->id, 'kind' => $item->kind, 'statement' => $item->statement, 'support' => $item->status, 'source' => $item->source?->id, 'origin' => $item->origin, 'reason' => $item->reason, 'labels' => $item->labels, 'category' => $item->category, 'requirements' => $item->requirements, 'related' => $item->related, 'design' => $item->data['design'] ?? [], 'metadata' => $item->data['metadata'] ?? [], 'test_references' => $item->data['tests'] ?? []];
    }
}
