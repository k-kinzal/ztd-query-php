<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CheckConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint>
 */
final class CheckConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $condition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName|null>|null $name Generated constructor operand
     * @param ErisGenerator<bool|null>|null $enforced Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $condition, ?ErisGenerator $name = null, ?ErisGenerator $enforced = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint(condition: $parts[0], name: $parts[1], enforced: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $condition,
            $name ?? Generators::constant(null),
            $enforced ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
