<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * One occurrence of a named table, view or common table as query input.
 *
 * Mirrors PostgreSQL's `RangeVar` with its `Alias` and an optional
 * `RangeTableSample`. The facts follow PG-TABLE-SHAPE-001.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM. Status: Implemented.
 *
 * @visibility public
 * @example Reading a named input
 *     $input = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM app.t AS x')->singleNamedInput();
 *     [$input->name()->schema?->value, $input->name()->name->value, $input->alias()?->value] // => ['app', 't', 'x']
 * @example Refusing column names without a correlation name
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), null, [new \SqlSemantics\Statement\Identifier\Name('a')]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class TableInput implements NamedRelation
{
    use Snapshot;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $columns;

    /**
     * @param RelationReference $table The relation named
     * @param Name|null $alias The correlation name
     * @param list<Name> $columns The column names written after the correlation name
     * @param TableSample|null $sample The TABLESAMPLE clause
     */
    public function __construct(public readonly RelationReference $table, public readonly ?Name $alias = null, array $columns = [], public readonly ?TableSample $sample = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Column aliases are names.');
        Check::input($alias !== null || $this->columns === [], 'Column aliases are written after a correlation name.');
    }

    /**
     * Answers the relation name.
     */
    public function name(): QualifiedName
    {
        return $this->table->name;
    }

    /**
     * Answers the correlation name.
     */
    public function alias(): ?Name
    {
        return $this->alias;
    }

    /**
     * Resolves the name and derives the row shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableShapes())->input($this, $derivation, $environment);
    }

    /**
     * Writes the relation, the alias and the sample.
     */
    public function render(Output $out): void
    {
        $out->node($this->table);
        (new AliasSpelling())->write($out, $this->alias, $this->columns);
        $out->node($this->sample);
    }
}
