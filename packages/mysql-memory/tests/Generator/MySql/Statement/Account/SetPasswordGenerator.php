<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SetPassword values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>
 */
final class SetPasswordGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Password|null> $password Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $replace Generated constructor operand
     * @param ErisGenerator<bool>|null $retainCurrent Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $user, ErisGenerator $password, ?ErisGenerator $replace = null, ?ErisGenerator $retainCurrent = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\SetPassword {
            return new \SqlSemantics\Platform\MySql\Statement\Account\SetPassword(user: $parts[0], password: $parts[1], replace: $parts[2], retainCurrent: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $user,
            $password,
            $replace ?? Generators::constant(null),
            $retainCurrent ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>>
     */
    public static function forms(): iterable
    {
        yield 'SetPassword/384' => new SetPasswordGenerator(
            user: Generators::constant(null),
            password: new PasswordGenerator(
                text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                function: Generators::constant(null)
            ),
            replace: Generators::constant(null),
            retainCurrent: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'SetPassword/additional/76' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new SetPasswordGenerator(
            user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('u'),
                    quote: Generators::constant(null)
                ),
                host: Generators::constant(null)
            )]),
            password: new PasswordGenerator(
                text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('x'),
                    radix: Generators::constant(null)
                ),
                function: PasswordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::OldPassword])
            ),
            replace: Generators::constant(null),
            retainCurrent: Generators::constant(false)
        )];
    }

}
