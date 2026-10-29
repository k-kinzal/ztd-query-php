<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SelectExpression values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression>
 */
final class SelectExpressionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Spelling\Layout|null>|null $layout Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile|null>|null $spellingProfile Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $alias = null, ?ErisGenerator $layout = null, ?ErisGenerator $mark = null, ?ErisGenerator $spellingProfile = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression {
            return new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(expression: $parts[0], alias: $parts[1], layout: $parts[2], mark: $parts[3], spellingProfile: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $expression,
            $alias ?? Generators::constant(null),
            $layout ?? Generators::constant(null),
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $spellingProfile ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
