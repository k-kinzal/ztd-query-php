<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type spelled `BIT` or `BIT VARYING`, with an optional length.
 *
 * Rule: PG-TYPE-BIT-001. `BIT` without a length is `bit(1)` as a type and
 * unconstrained when it types a constant; `BIT VARYING` without a length is
 * unlimited. Facts: PG-TYPE-MODIFIER-001.
 * Source: https://www.postgresql.org/docs/17/datatype-bit.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the default length of a bit type
 *     $designation = new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation(false);
 *     $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->context([]);
 *     $designation->typeFact($context, false)->descriptor->name() // => 'bit(1)'
 */
final class BitDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @var list<Scalar> The modifier expressions in order
     */
    public readonly array $modifiers;

    /**
     * @param bool $varying Whether VARYING is written
     * @param list<Scalar> $modifiers The modifier expressions in order
     */
    public function __construct(public readonly bool $varying, array $modifiers = [])
    {
        $this->modifiers = Check::listOf($modifiers, Scalar::class, 'Type modifiers are expressions.');
    }

    /**
     * Answers the bit type with its length.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        if ($this->modifiers === [] && !$this->varying && !$constant) {
            return new Known(new Parameterized(Builtin::Bit, 1));
        }

        return (new Modifiers())->apply($this->varying ? Builtin::Varbit : Builtin::Bit, $this->modifiers);
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name($this->varying ? Builtin::Varbit->value : Builtin::Bit->value);
    }

    /**
     * Derives the modifier expressions.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->modifiers as $modifier) {
            $derivation->scalar($modifier, $environment);
        }
    }

    /**
     * Writes the keywords and the length.
     */
    public function render(Output $out): void
    {
        $out->keyword('BIT');
        if ($this->varying) {
            $out->keyword('VARYING');
        }
        if ($this->modifiers !== []) {
            $out->symbol('(')->list($this->modifiers)->symbol(')');
        }
    }
}
