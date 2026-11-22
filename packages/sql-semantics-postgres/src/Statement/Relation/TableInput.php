<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\NamedRelation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;

/**
 * One occurrence of a named table, view or common table as query input.
 *
 * Mirrors PostgreSQL's `RangeVar` with its `Alias` and an optional
 * `RangeTableSample`. The facts follow PG-TABLE-SHAPE-001.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing column names without a correlation name
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'))), null, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
