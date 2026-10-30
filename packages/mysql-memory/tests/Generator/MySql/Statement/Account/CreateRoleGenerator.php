<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use Tests\Generator\MySql\Statement\Account\Problem\Role\RefusedCreateRoleGenerator;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates complete role creation requests, including conditional and refused batches.
 *
 * @implements ErisGenerator<CreateRole>
 */
final class CreateRoleGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<CreateRole>
     */
    private ErisGenerator $delegate;

    /**
     * Generates complete CreateRole values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<CreateRole>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No CreateRole forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): CreateRole {
            if (!$statement instanceof CreateRole) {
                throw new LogicException('Publishing CreateRole must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     * @return ErisGenerator<CreateRole>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        $profile = new LanguageProfile($release);
        $requests = Generators::bind(Generators::bool(), static fn (bool $conditional): ErisGenerator => Generators::bind(new AccountNameGenerator(), static fn (AccountName $first): ErisGenerator => Generators::map(static fn (array $rest): CreateRole => new CreateRole($conditional, [$first, ...$rest], $profile), Generators::seq(new AccountNameGenerator()))));
        return Generators::oneOf($requests, Generators::bind(new RefusedCreateRoleGenerator(), static fn (RefusedCreateRole $condition): ErisGenerator => Generators::map(static fn (bool $conditional): CreateRole => new CreateRole($conditional, $condition->roles, $profile), Generators::bool())));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CreateRole>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateRole>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool> $ifNotExists The generated semantic operand
     * @param ErisGenerator<list<AccountName>> $roles The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<CreateRole>
     */
    public static function fromOperands(ErisGenerator $ifNotExists, ErisGenerator $roles, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): CreateRole => new CreateRole(ifNotExists: $parts[0], roles: $parts[1], profile: $parts[2], client: $parts[3]), Generators::tuple($ifNotExists, $roles, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<CreateRole>>
     */
    public static function forms(): iterable
    {
        yield 'account operands' => self::operands(GrammarRelease::MySql847);
    }

}
