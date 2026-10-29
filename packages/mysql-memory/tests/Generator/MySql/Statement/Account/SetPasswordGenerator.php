<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete SetPassword values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No SetPassword forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\SetPassword {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetPassword) {
                throw new LogicException('Publishing SetPassword must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Password|null> $password Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $replace Generated constructor operand
     * @param ErisGenerator<bool>|null $retainCurrent Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator>|null $operator Generated assignment sign
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>
     */
    public static function operands(ErisGenerator $user, ErisGenerator $password, ?ErisGenerator $replace = null, ?ErisGenerator $retainCurrent = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\SetPassword {
            return new \SqlSemantics\Platform\MySql\Statement\Account\SetPassword(user: $parts[0], password: $parts[1], replace: $parts[2], retainCurrent: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], operator: $parts[6]);
        }, Generators::tuple($user, $password, $replace ?? Generators::constant(null), $retainCurrent ?? Generators::constant(false), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $operator ?? new \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>>
     */
    public static function forms(): iterable
    {
        yield 'SetPassword/384' => SetPasswordGenerator::operands(
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
        yield 'SetPassword/additional/76' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, SetPasswordGenerator::operands(
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
