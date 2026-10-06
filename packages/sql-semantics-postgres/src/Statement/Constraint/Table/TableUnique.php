<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyClauses;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A UNIQUE table constraint: no two rows hold the same values in the key columns.
 *
 * Mirrors `CONSTR_UNIQUE` with `nulls_not_distinct`, `keys`, `including`,
 * `options`, `indexspace` and the attributes. Each key and included column
 * must be a column of the table (PG-KEY-COLUMNS-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a unique constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, b int, UNIQUE NULLS DISTINCT (a) INCLUDE (b) DEFERRABLE)');
 *     $create->toString() // => 'CREATE TABLE t (a INT, b INT, UNIQUE NULLS DISTINCT (a) INCLUDE (b) DEFERRABLE)'
 */
final class TableUnique implements Constraint
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The key columns
     */
    public readonly array $columns;

    /**
     * @var list<Name> The included columns
     */
    public readonly array $included;

    /**
     * @var list<Definition> The storage parameters of the index
     */
    public readonly array $options;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param list<Name> $columns The key columns; at least one
     * @param bool|null $nullsDistinct True for NULLS DISTINCT, false for NULLS NOT DISTINCT, null when not written
     * @param list<Name> $included The included columns
     * @param list<Definition> $options The storage parameters of the index
     * @param Name|null $tablespace The tablespace of the index
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(array $columns, public readonly ?bool $nullsDistinct = null, array $included = [], array $options = [], public readonly ?Name $tablespace = null, array $attributes = [], public readonly ?Name $name = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A unique constraint has at least one key column.', 1);
        $this->included = Check::listOf($included, Name::class, 'Included columns are names.');
        $this->options = Check::listOf($options, Definition::class, 'Index storage parameters are definitions.');
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Unique;
    }

    /**
     * Checks the columns against the table and derives the parameters.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new KeyClauses())->derive($derivation, $environment, [...$this->columns, ...$this->included], $this->options);
        (new Attributes())->report($derivation, $this->attributes, 'UNIQUE', true, false, false);
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
        (new KeyClauses())->write($out, $this->columns, $this->included, $this->options, $this->tablespace);
        $writing->sequence($out, $this->attributes);
    }
}
