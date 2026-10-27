<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Level;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel>
 */
final class DatabaseLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $database Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $database, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel(database: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $database,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
