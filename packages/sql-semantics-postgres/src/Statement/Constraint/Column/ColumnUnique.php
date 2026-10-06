<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A UNIQUE column constraint: no two rows hold the same value in the column.
 *
 * Mirrors `CONSTR_UNIQUE` with `nulls_not_distinct`, `options` and
 * `indexspace`. NULLS DISTINCT is the default and is kept when written.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading how NULL values count
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int UNIQUE NULLS NOT DISTINCT)');
 *     $create->statement->definition->elements[0]->qualifiers[0]->nullsDistinct // => false
 */
final class ColumnUnique implements Constraint
{
    use Snapshot;

    /**
     * @var list<Definition> The storage parameters of the index
     */
    public readonly array $options;

    /**
     * @param bool|null $nullsDistinct True for NULLS DISTINCT, false for NULLS NOT DISTINCT, null when not written
     * @param list<Definition> $options The storage parameters of the index
     * @param Name|null $tablespace The tablespace of the index
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly ?bool $nullsDistinct = null, array $options = [], public readonly ?Name $tablespace = null, public readonly ?Name $name = null)
    {
        $this->options = Check::listOf($options, Definition::class, 'Index storage parameters are definitions.');
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Unique;
    }

    /**
     * Derives the storage parameters.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword('UNIQUE');
        $writing->nullTreatment($out, $this->nullsDistinct);
        $writing->indexParameters($out, $this->options, $this->tablespace);
    }
}
