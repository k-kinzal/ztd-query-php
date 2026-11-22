<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Step;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;

/**
 * The indirection step `[ index ]`: one element of an array, or of another subscriptable value.
 *
 * Mirrors an `A_Indices` item with `is_slice` false.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-SUBSCRIPTS.
 *
 * @visibility SqlSemantics
 */
final class Subscript implements IndirectionStep
{
    use Snapshot;

    /**
     * @param Scalar $index The subscript
     */
    public function __construct(public readonly Scalar $index)
    {
    }

    /**
     * Answers null: a subscript selects no field.
     */
    public function field(): ?Name
    {
        return null;
    }

    /**
     * Derives the subscript.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->index, $environment);
    }

    /**
     * Writes the subscript in brackets.
     */
    public function render(Output $out): void
    {
        $out->symbol('[')->node($this->index)->symbol(']');
    }
}
