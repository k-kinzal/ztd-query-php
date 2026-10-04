<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Qualifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The definition of one column: its name, type, storage, compression, foreign-data options and constraints.
 *
 * Mirrors PostgreSQL's `ColumnDef` with the constraint list `ColQualList`
 * builds. The qualifiers are kept in the order written: constraints,
 * deferral attributes (which apply to the constraint before them) and a
 * collation. Their problems are reported by PG-COLUMN-QUALIFIERS-001; their
 * expressions are derived in the environment of the table.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a column definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a text STORAGE external COMPRESSION lz4 COLLATE "C" NOT NULL)');
 *     $column = $create->statement->definition->elements[0];
 *     [$column->name->value, $column->storage->method->value, $column->compression->method->value, count($column->qualifiers)] // => ['a', 'external', 'lz4', 2]
 */
final class ColumnDefinition implements Clause
{
    use Snapshot;

    /**
     * @var list<GenericOption> The foreign-data options
     */
    public readonly array $options;

    /**
     * @var list<Clause> The constraints, deferral attributes and collation, in the order written
     */
    public readonly array $qualifiers;

    /**
     * @param Name $name The column name
     * @param TypeName $type The data type
     * @param ColumnStorage|null $storage The STORAGE clause
     * @param ColumnCompression|null $compression The COMPRESSION clause
     * @param list<GenericOption> $options The foreign-data options
     * @param list<Clause> $qualifiers The constraints, deferral attributes and collation, in the order written
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeName $type,
        public readonly ?ColumnStorage $storage = null,
        public readonly ?ColumnCompression $compression = null,
        array $options = [],
        array $qualifiers = [],
    ) {
        $this->options = Check::listOf($options, GenericOption::class, 'Foreign-data options are generic options.');
        $this->qualifiers = (new Qualifiers())->checked($qualifiers);
    }

    /**
     * Derives the type and the qualifiers in the environment of the table and reports their problems.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
        (new Qualifiers())->derive($derivation, $environment, $this->name, $this->qualifiers);
    }

    /**
     * Writes the column definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name)->node($this->type)->node($this->storage)->node($this->compression);
        if ($this->options !== []) {
            $out->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
        }
        foreach ($this->qualifiers as $qualifier) {
            $out->node($qualifier);
        }
    }
}
