<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SessionUser values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser>
 */
final class SessionUserGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias|null>|null $alias Generated keyword synonym
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser {
            return new \SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser(inputSource: $parts[0], alias: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $inputSource ?? Generators::constant(null),
            $alias ?? \Tests\Generator\MySql\Statement\Call\Keyword\KeywordAliasGenerator::forFunction(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User)
        ));
    }
}
