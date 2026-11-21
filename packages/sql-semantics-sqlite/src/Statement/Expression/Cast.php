<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;

/**
 * A conversion of a value to the storage class the affinity of a type name selects.
 *
 * Rule: SQLITE-CAST-001. The affinity of the type name decides the result:
 * INTEGER, TEXT, BLOB and REAL affinity give that storage class; NUMERIC
 * affinity, which an absent type name also has, gives INTEGER or REAL. A NULL
 * operand stays NULL, so the NULL fact is that of the operand.
 * Source: https://sqlite.org/lang_expr.html#castexpr. Status: Implemented.
 *
 * @visibility public
 * @example Reading the type of a CAST
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT CAST(a AS VARCHAR(10)) FROM t');
 *     [$query->statement->columns[0]->expression->target->text(), $query->field(0)->type->descriptor] // => ['VARCHAR', \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Text]
 */
final class Cast implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The converted expression
     * @param TypeName|null $target The type name; null when none is written
     */
    public function __construct(public readonly Scalar $operand, public readonly ?TypeName $target = null)
    {
    }

    /**
     * Derives the storage class from the affinity of the type name.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        foreach ($this->target === null ? [] : $this->target->arguments as $argument) {
            $derivation->scalar($argument->number, $environment);
        }
        if ($fact->type instanceof NullOnly) {
            return new ScalarFact($fact->type, $fact->nullability);
        }
        $storages = match ($this->target?->affinity() ?? Affinity::Numeric) {
            Affinity::Integer => [Storage::Integer],
            Affinity::Text => [Storage::Text],
            Affinity::Blob => [Storage::Blob],
            Affinity::Real => [Storage::Real],
            Affinity::Numeric => [Storage::Integer, Storage::Real],
        };

        return new ScalarFact((new Storages())->fact($storages), $fact->nullability);
    }

    /**
     * Writes the conversion.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AS')->node($this->target)->symbol(')');
    }
}
