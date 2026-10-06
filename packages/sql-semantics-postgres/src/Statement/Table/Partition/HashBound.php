<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A hash partition bound: FOR VALUES WITH (MODULUS m, REMAINDER r).
 *
 * Mirrors `PartitionBoundSpec` with `PARTITION_STRATEGY_HASH`. An item other
 * than `modulus` and `remainder` is reported.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Writing a hash bound
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (modulus 4, remainder 0)')->toString() // => 'CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (modulus 4, remainder 0)'
 */
final class HashBound implements PartitionBound
{
    use Snapshot;

    /**
     * @var non-empty-list<HashModulus> The items
     */
    public readonly array $items;

    /**
     * @param list<HashModulus> $items The items; at least one
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, HashModulus::class, 'A hash bound has at least one item.', 1);
    }

    /**
     * Reports the items the server does not recognize.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->items as $item) {
            if ($item->name->value !== 'modulus' && $item->name->value !== 'remainder') {
                $derivation->report(new DefinitionProblem(DefinitionRule::HashBoundOption, $item->name));
            }
        }
    }

    /**
     * Writes the bound.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR', 'VALUES', 'WITH')->symbol('(')->list($this->items)->symbol(')');
    }
}
