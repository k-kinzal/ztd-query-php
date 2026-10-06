<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Step;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The indirection step `[ index ]`: one element of an array, or of another subscriptable value.
 *
 * Mirrors an `A_Indices` item with `is_slice` false.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-SUBSCRIPTS.
 *
 * @visibility public
 * @example Reading a subscript
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT $1[2]');
 *     $query->field(0)->expression->steps[0]->index->value->digits // => '2'
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
