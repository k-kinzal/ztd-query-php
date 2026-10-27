<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Modifier;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlgorithmOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption>
 */
final class AlgorithmOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $algorithm Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $algorithm, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(algorithm: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $algorithm,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
