<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * One column of the result of a function declared `RETURNS TABLE ( ... )`.
 *
 * Mirrors PostgreSQL's `FunctionParameter` with mode `TABLE`: an output
 * parameter that is a column of each result row.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility SqlSemantics
 * @example Reading the column name
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('int4')])));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('id'), $type))->name->value // => 'id'
 */
final class ResultColumn implements Clause
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeName $type The column type
     */
    public function __construct(public readonly Name $name, public readonly TypeName $type)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name and the type.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Routine)->node($this->type);
    }
}
