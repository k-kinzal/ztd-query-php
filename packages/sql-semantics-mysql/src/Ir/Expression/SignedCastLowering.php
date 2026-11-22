<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression;

use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Resolves explicit signed conversion projections.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SignedCastLowering
{
    /**
     * Binds each signed conversion and its exact operand before publication.
     *
     * @throws InvalidConstruction When the target or its input has another role
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding): ?SignedCast
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof Cast) {
            return null;
        }
        Check::input(!$expression->array && $expression->target->kind === CastKind::Signed, 'A projection cast must have the scalar MySQL SIGNED target.');

        $value = $this->lower($expression->operand, $binding) ?? (new ExpressionLowering())->lower($expression->operand, $binding);
        Check::input(!$value instanceof IntegerRange, 'A signed cast requires a checked signed scalar input.');

        return new SignedCast($value);
    }
}
