<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Level;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CurrentDatabaseLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel>
 */
final class CurrentDatabaseLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel(inputSource: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $inputSource ?? Generators::constant(null)
        ));
    }
}
