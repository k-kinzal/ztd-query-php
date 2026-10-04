<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * PARTITION BY: the strategy and the key of a partitioned table.
 *
 * Mirrors PostgreSQL's `PartitionSpec`. The grammar reads the strategy as a
 * name; the server accepts LIST, RANGE and HASH in any letter case and
 * reports any other word.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-partitioning.html.
 *
 * @visibility public
 * @example Reading the strategy
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int) PARTITION BY HASH (a)');
 *     $create->statement->partitioning->strategy->value // => 'hash'
 */
final class PartitionSpec implements Clause
{
    use Snapshot;

    /**
     * @var non-empty-list<PartitionElement> The key
     */
    public readonly array $elements;

    /**
     * @param Name $strategy The strategy word
     * @param list<PartitionElement> $elements The key; at least one element
     */
    public function __construct(public readonly Name $strategy, array $elements)
    {
        $this->elements = Check::listOf($elements, PartitionElement::class, 'A partition key has at least one element.', 1);
    }

    /**
     * Derives the key against the table and reports an unknown strategy.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        if (!in_array(strtolower($this->strategy->value), ['list', 'range', 'hash'], true)) {
            $derivation->report(new DefinitionProblem(DefinitionRule::PartitionStrategy, $this->strategy));
        }
        foreach ($this->elements as $element) {
            $element->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes PARTITION BY, the strategy and the key.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARTITION', 'BY')->name($this->strategy)->symbol('(')->list($this->elements)->symbol(')');
    }
}
