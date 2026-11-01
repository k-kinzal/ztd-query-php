<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;

/**
 * The table an INSERT, UPDATE, DELETE, MERGE or COPY writes to or reads from, with its correlation name.
 *
 * Mirrors the `relation` `RangeVar` of PostgreSQL's `InsertStmt`,
 * `UpdateStmt`, `DeleteStmt`, `MergeStmt` and `CopyStmt`. The facts follow
 * PG-TARGET-TABLE-001.
 * Source: https://www.postgresql.org/docs/17/sql-update.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the table a statement writes to
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('UPDATE ONLY app.t AS x SET a = 1');
 *     [$update->target->name()->schema?->value, $update->target->table->only, $update->target->alias()?->value] // => ['app', true, 'x']
 */
final class TargetTable implements NamedRelation, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RelationReference $table The table, with ONLY when its descendant tables are excluded
     * @param Name|null $alias The correlation name
     */
    public function __construct(public readonly RelationReference $table, public readonly ?Name $alias = null, ?RelationFact $relationInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
    }

    /**
     * Answers the table name.
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
     * Resolves the name among the declared relations and derives the row shape of the table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->fact($this, $derivation);
    }

    /**
     * Writes the table and the correlation name.
     */
    public function render(Output $out): void
    {
        $out->node($this->table);
        (new AliasSpelling())->write($out, $this->alias);
    }
}
