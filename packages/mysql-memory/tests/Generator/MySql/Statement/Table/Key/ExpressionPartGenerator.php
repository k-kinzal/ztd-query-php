<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExpressionPart values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart>
 */
final class ExpressionPartGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Direction|null>|null $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $direction = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart(expression: $parts[0], direction: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $expression,
            $direction ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
