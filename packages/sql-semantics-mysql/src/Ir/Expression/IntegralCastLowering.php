<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression;

use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter as ParsedParameter;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Resolves the signed and unsigned targets of explicit integral projections.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IntegralCastLowering
{
    /**
     * Binds each integral conversion and its exact operand before publication.
     *
     * @throws InvalidConstruction When the target or its input has another role
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding): SignedCast|UnsignedCast|null
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof Cast) {
            return null;
        }
        $target = $expression->target->kind;
        Check::input(
            !$expression->array && ($target === CastKind::Signed || $target === CastKind::Unsigned),
            'A projection cast requires a checked scalar integer target.'
        );
        $operand = $expression->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        Check::input(
            $target !== CastKind::Unsigned || !$operand instanceof ParsedParameter,
            'A projection cast must have the scalar MySQL SIGNED target.'
        );

        $value = $this->lower($operand, $binding) ?? (new ExpressionLowering())->lower($operand, $binding, true);
        Check::input(!$value instanceof IntegerRange && !$value instanceof IntegerMembership, 'An integer cast requires a checked integral scalar input.');

        return $target === CastKind::Signed ? new SignedCast($value) : new UnsignedCast($value);
    }
}
