<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CreateUser values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\CreateUser>
 */
final class CreateUserGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\CreateUser> */
    use Composition;

    /**
     * Generates complete CreateUser values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\CreateUser>|null $operands A constrained form of this concrete result
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

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                $forms[] = Generators::map(static fn (array $parts): \SqlSemantics\Platform\MySql\Statement\Statement => $parts[0], Problem\InvalidUserAttributeGenerator::statements());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No CreateUser forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\CreateUser {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateUser) {
                throw new LogicException('Publishing CreateUser must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>> $users Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName>>|null $defaultRoles Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit>>|null $resources Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment|null>|null $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\CreateUser>
     */
    public static function operands(ErisGenerator $ifNotExists, ErisGenerator $users, ?ErisGenerator $defaultRoles = null, ?ErisGenerator $tls = null, ?ErisGenerator $resources = null, ?ErisGenerator $options = null, ?ErisGenerator $comment = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\CreateUser {
            return new \SqlSemantics\Platform\MySql\Statement\Account\CreateUser(ifNotExists: $parts[0], users: $parts[1], defaultRoles: $parts[2], tls: $parts[3], resources: $parts[4], options: $parts[5], comment: $parts[6], statementInformation: $parts[7], inputSource: $parts[8]);
        }, Generators::tuple($ifNotExists, $users, $defaultRoles ?? Generators::constant([]), $tls ?? Generators::constant(null), $resources ?? Generators::constant([]), $options ?? Generators::constant([]), $comment ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\CreateUser>>
     */
    public static function forms(): iterable
    {
        yield 'CreateUser/139' => CreateUserGenerator::operands(
            ifNotExists: Generators::constant(false),
            users: Generators::tuple(
                new User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )
            ),
            defaultRoles: Generators::constant([]),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
    }

}
