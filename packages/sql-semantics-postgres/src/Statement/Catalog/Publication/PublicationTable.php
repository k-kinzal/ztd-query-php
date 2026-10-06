<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublishedTables;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A table as an item of a publication: `[ TABLE ] [ ONLY ] name [ * ] [ ( column, ... ) ] [ WHERE ( expression ) ]`.
 *
 * Mirrors `PublicationObjSpec` of type TABLE with its `PublicationTable`.
 * The table is a relation occurrence: its name is resolved, the column list
 * names its columns and the row filter sees its row (PG-PUBLICATION-TABLE-001).
 * A trailing `*` changes nothing, but marks a bare name continuing a list as a
 * table, so it is kept.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html.
 *
 * @visibility public
 * @example Resolving a published table and its row filter
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
 *     $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)]);
 *     $operation = $semantics->analyze('CREATE PUBLICATION p FOR TABLE t (a) WHERE (a > 0)', [$t]);
 *     [$operation->facts->relation($operation->statement->objects[0])->table->table === $t, $operation->facts->diagnostics] // => [true, []]
 */
final class PublicationTable implements PublicationMember, Relation
{
    use Snapshot;

    /**
     * @var list<Name> The published columns; empty for every column
     */
    public readonly array $columns;

    /**
     * @param RelationReference $table The table
     * @param list<Name> $columns The published columns; empty for every column
     * @param Scalar|null $where The row filter, when WHERE is written
     * @param bool $keyword Whether TABLE is written
     * @param bool $star Whether a trailing `*` is written
     */
    public function __construct(public readonly RelationReference $table, array $columns = [], public readonly ?Scalar $where = null, public readonly bool $keyword = true, public readonly bool $star = false)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Published columns are names.');
        Check::input(!($star && $table->only), 'A table name is written with ONLY or with a trailing star, not both.');
    }

    /**
     * Tells whether TABLE is written.
     */
    public function introduced(): bool
    {
        return $this->keyword;
    }

    /**
     * Tells whether the item is a bare name, which a list reads as the kind of the item before it.
     */
    public function bare(): bool
    {
        return !$this->keyword && !$this->star && !$this->table->only && $this->table->name->schema === null && $this->columns === [] && $this->where === null;
    }

    /**
     * Resolves the table, checks the column list and derives the row filter.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new PublishedTables())->derive($this, $derivation, $environment);
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        if ($this->keyword) {
            $out->keyword('TABLE');
        }
        $out->node($this->table);
        if ($this->star) {
            $out->symbol('*');
        }
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->symbol('(')->node($this->where)->symbol(')');
        }
    }
}
