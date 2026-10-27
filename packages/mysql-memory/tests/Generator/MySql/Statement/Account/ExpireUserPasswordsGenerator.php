<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExpireUserPasswords values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords>
 */
final class ExpireUserPasswordsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\Account>> $users Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $users, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords {
            return new \SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords(users: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $users,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'ExpireUserPasswords/additional/77' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new ExpireUserPasswordsGenerator(
            users: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                    user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('a'),
                        quote: Generators::constant(null)
                    ),
                    host: Generators::constant(null)
                )]),
                new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                )])
            )
        )];
    }

}
