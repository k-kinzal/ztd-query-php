<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\TypeLookup;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type of an existing column, written `table.column%TYPE`.
 *
 * Rule: PG-TYPE-COLUMN-001. The name before the last dot is a relation
 * looked up by CORE-TABLE-LOOKUP-001 and the last part is one of its columns.
 * Facts: the declared type of the column; a missing relation or column is a
 * diagnostic; an undeclared relation is the missing input.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the column a type is copied from
 *     $name = new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('a')]);
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation($name))->catalogName()->value // => 'a'
 */
final class ColumnDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param DottedName $name The relation name followed by the column name; at least two parts
     */
    public function __construct(public readonly DottedName $name)
    {
        Check::input(count($name->parts) >= 2, 'A column type reference names a relation and a column.');
    }

    /**
     * Answers the declared type of the column.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        return (new TypeLookup())->column($context, $this->name);
    }

    /**
     * Answers the column name.
     */
    public function catalogName(): Name
    {
        return $this->name->last();
    }

    /**
     * Derives nothing: the reference holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the name followed by `%TYPE`.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->name->parts, NameUse::Routine);
        $out->symbol('%')->keyword('TYPE');
    }
}
