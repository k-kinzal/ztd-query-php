<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Characteristic;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RoutineLanguage values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage>
 */
final class RoutineLanguageGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $external Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $external = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage(external: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $external ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
