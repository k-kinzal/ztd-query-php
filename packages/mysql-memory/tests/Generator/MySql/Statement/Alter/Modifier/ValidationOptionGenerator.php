<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Modifier;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ValidationOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption>
 */
final class ValidationOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $validate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $validate, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption(validate: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $validate,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
