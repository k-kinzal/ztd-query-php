<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Supplies exactly the two named bounds required by gram.y/PartitionBoundSpec.
 */
final class HashPartitionBoundRule implements RewriteRule
{
    /**
     * Retains the first two integer values; the lexical handler chooses both distinct bound names.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('PartitionBoundSpec') as $id) {
            $bounds = $sequence->child($id, 'hash_partbound');
            $range = $bounds === null ? null : $sequence->range($bounds->id);
            if ($range === null) {
                continue;
            }
            $values = [];
            foreach (array_reverse($sequence->occurrences('hash_partbound_elem')) as $element) {
                $value = $sequence->child($element, 'Iconst');
                $valueRange = $value === null ? null : $sequence->range($value->id);
                if ($valueRange !== null && $sequence->terminals[$valueRange[0]]->ancestor('PartitionBoundSpec') === $id) {
                    $values[] = array_slice($sequence->terminals, $valueRange[0], $valueRange[1] - $valueRange[0]);
                }
            }
            $source = 'gram.y:hash-partition-bound';
            $output = [$sequence->insertedFor('HASH_BOUND_NAME', $bounds->id, $source)];
            array_push($output, ...($values[0] ?? [$sequence->insertedFor('ICONST', $bounds->id, $source, 1)]));
            $output[] = $sequence->insertedFor(',', $bounds->id, $source, 2);
            $output[] = $sequence->insertedFor('HASH_BOUND_NAME', $bounds->id, $source, 3);
            array_push($output, ...($values[1] ?? [$sequence->insertedFor('ICONST', $bounds->id, $source, 4)]));
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], $output, $source);
        }
        return $sequence;
    }
}
