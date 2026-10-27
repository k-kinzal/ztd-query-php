<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterRegistration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration>
 */
final class AlterRegistrationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|\SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\Factor> $factor Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep> $step Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $challengeResponse Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $user, ErisGenerator $factor, ErisGenerator $step, ?ErisGenerator $challengeResponse = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration {
            return new \SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration(ifExists: $parts[0], user: $parts[1], factor: $parts[2], step: $parts[3], challengeResponse: $parts[4], statementInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $user,
            $factor,
            $step,
            $challengeResponse ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration>>
     */
    public static function forms(): iterable
    {
        yield 'AlterRegistration/249' => new AlterRegistrationGenerator(
            ifExists: Generators::constant(false),
            user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            factor: User\FactorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second]),
            step: User\RegistrationStepGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep::Unregister]),
            challengeResponse: Generators::constant(null)
        );
    }

}
