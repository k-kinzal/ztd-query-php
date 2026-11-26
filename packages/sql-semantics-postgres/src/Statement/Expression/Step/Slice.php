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
 * The indirection step `[ lower : upper ]`: a slice of an array; an omitted bound is the array's own bound.
 *
 * Mirrors an `A_Indices` item with `is_slice` true.
 * Source: https://www.postgresql.org/docs/17/arrays.html#ARRAYS-ACCESSING.
 *
 * @visibility SqlSemantics
 */
final class Slice implements IndirectionStep
{
    use Snapshot;

    /**
     * @param Scalar|null $lower The lower bound, or null when omitted
     * @param Scalar|null $upper The upper bound, or null when omitted
     */
    public function __construct(public readonly ?Scalar $lower, public readonly ?Scalar $upper)
    {
    }

    /**
     * Answers null: a slice selects no field.
     */
    public function field(): ?Name
    {
        return null;
    }

    /**
     * Derives the written bounds.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ([$this->lower, $this->upper] as $bound) {
            if ($bound !== null) {
                $derivation->scalar($bound, $environment);
            }
        }
    }

    /**
     * Writes the bounds around a colon in brackets.
     */
    public function render(Output $out): void
    {
        $out->symbol('[')->node($this->lower)->symbol(':')->node($this->upper)->symbol(']');
    }
}
