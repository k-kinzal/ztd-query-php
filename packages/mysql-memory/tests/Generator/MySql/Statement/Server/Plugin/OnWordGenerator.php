<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OnWord values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord>
 */
final class OnWordGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord(scalarInformation: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
