<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LegacyDatabaseName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName>
 */
final class LegacyDatabaseNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $suffix Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $suffix, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName(suffix: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $suffix,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
