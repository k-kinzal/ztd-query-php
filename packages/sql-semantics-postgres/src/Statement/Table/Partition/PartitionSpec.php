<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\GeneratedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;

/**
 * PARTITION BY: the strategy and the key of a partitioned table.
 *
 * Mirrors PostgreSQL's `PartitionSpec`. The grammar reads the strategy as a
 * name; the server accepts LIST, RANGE and HASH in any letter case and
 * reports any other word.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-partitioning.html.
 *
 * @visibility SqlSemantics
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
        $generated = new GeneratedColumns();
        foreach ($this->elements as $element) {
            $element->deriveClause($derivation, $environment);
            $generated->partitionKey($derivation, $element->key);
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
