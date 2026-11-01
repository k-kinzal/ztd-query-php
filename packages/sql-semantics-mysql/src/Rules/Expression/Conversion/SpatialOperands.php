<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Resolves numeric operator constraints before their operand values are evaluated.
 * Geometry cannot undergo arithmetic. Only legacy bit operations accept geometry
 * as numeric bytes; a binary cast instead supplies an ordinary string domain.
 * Verified by native text and prepared observations on MySQL 5.7 and 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SpatialOperands
{
    /**
     * @param list<TypeFact> $types The resolved operand facts, independent of runtime values
     */
    public function check(Derivation $derivation, ArithmeticOperator|UnaryOperator $operator, array $types): void
    {
        if ($derivation->context->profile->grammar === GrammarRelease::MySql5651) {
            return;
        }
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $bitwise = $operator instanceof ArithmeticOperator ? $operator->bitwise() : $operator === UnaryOperator::Invert;
        if (($legacy && $bitwise) || ($operator instanceof UnaryOperator && !in_array($operator, [UnaryOperator::Minus, UnaryOperator::Invert], true))) {
            return;
        }
        foreach ($types as $type) {
            if ($type instanceof Known && TypeClass::of($type->descriptor) === TypeClass::Spatial) {
                $derivation->report(new SpatialArithmetic($operator));
                return;
            }
        }
    }
}
