<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes KeywordCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall>
 */
final class KeywordCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction> $function Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>> $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $parentheses = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall {
            return new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(function: $parts[0], arguments: $parts[1], parentheses: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $function,
            $arguments,
            $parentheses ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
