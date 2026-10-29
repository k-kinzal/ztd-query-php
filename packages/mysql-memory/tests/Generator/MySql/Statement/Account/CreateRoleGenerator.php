<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
     * Composes operands with Eris, retaining their relationships during shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $profile = new LanguageProfile($release);
        $requests = \Tests\Generator\Operands::bind(Generators::bool(), static fn (bool $conditional): ErisGenerator => \Tests\Generator\Operands::bind(new AccountNameGenerator(), static fn (AccountName $first): ErisGenerator => Generators::map(static fn (array $rest): CreateRole => new CreateRole($conditional, [$first, ...$rest], $profile), \Tests\Generator\Operands::seq(new AccountNameGenerator()))));
        $this->delegate = Generators::oneOf(
            $requests,
            \Tests\Generator\Operands::bind(new RefusedCreateRoleGenerator(), static fn (RefusedCreateRole $condition): ErisGenerator => Generators::map(static fn (bool $conditional): CreateRole => new CreateRole($conditional, $condition->roles, $profile), Generators::bool())),
        );
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
        return Generators::map(static fn (array $parts): CreateRole => new CreateRole(ifNotExists: $parts[0], roles: $parts[1], profile: $parts[2], client: $parts[3]), \Tests\Generator\Operands::tuple($ifNotExists, $roles, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<CreateRole>>
     */
    public static function forms(): iterable
    {
        yield 'CreateRole/25' => CreateRoleGenerator::fromOperands(
            ifNotExists: Generators::constant(false),
            roles: \Tests\Generator\Operands::tuple(
                AccountNameGenerator::fromOperands(
                    user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    host: Generators::constant(null)
                )
            ),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
    }

}
