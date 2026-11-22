<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\NamedRelation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;

/**
 * The table an INSERT, UPDATE, DELETE, MERGE or COPY writes to or reads from, with its correlation name.
 *
 * Mirrors the `relation` `RangeVar` of PostgreSQL's `InsertStmt`,
 * `UpdateStmt`, `DeleteStmt`, `MergeStmt` and `CopyStmt`. The facts follow
 * PG-TARGET-TABLE-001.
 * Source: https://www.postgresql.org/docs/17/sql-update.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TargetTable implements NamedRelation
{
    use Snapshot;

    /**
     * @param RelationReference $table The table, with ONLY when its descendant tables are excluded
     * @param Name|null $alias The correlation name
     */
    public function __construct(public readonly RelationReference $table, public readonly ?Name $alias = null)
    {
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
