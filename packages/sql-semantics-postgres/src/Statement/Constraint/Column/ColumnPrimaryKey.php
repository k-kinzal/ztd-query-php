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
 * A PRIMARY KEY column constraint: the column is unique and never NULL.
 *
 * Mirrors `CONSTR_PRIMARY` with `options` and `indexspace`.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example A primary key column is never NULL
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int PRIMARY KEY)');
 *     $create->declarations()[0]->columns[0]->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
final class ColumnPrimaryKey implements Constraint
{
    use Snapshot;

    /**
     * @var list<Definition> The storage parameters of the index
     */
    public readonly array $options;

    /**
     * @param list<Definition> $options The storage parameters of the index
     * @param Name|null $tablespace The tablespace of the index
     * @param Name|null $name The constraint name
     */
    public function __construct(array $options = [], public readonly ?Name $tablespace = null, public readonly ?Name $name = null)
    {
        $this->options = Check::listOf($options, Definition::class, 'Index storage parameters are definitions.');
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::PrimaryKey;
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
        $out->keyword('PRIMARY', 'KEY');
        $writing->indexParameters($out, $this->options, $this->tablespace);
    }
}
