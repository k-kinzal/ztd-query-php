<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Step;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The indirection step `.*`: every field of a composite value.
 *
 * Mirrors an `A_Star` item. It may only end an indirection; in a select list
 * or a row constructor it expands to the fields, elsewhere it stands for the
 * whole composite value. Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE.
 *
 * @visibility public
 * @example Writing every field of a parameter
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ($1).*')->toString() // => 'SELECT ($1).*'
 */
final class AllFields implements IndirectionStep
{
    use Snapshot;

    /**
     * Answers null: the step selects every field, not one.
     */
    public function field(): ?Name
    {
        return null;
    }

    /**
     * Derives nothing: the step holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the dot and the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('.')->symbol('*');
    }
}
