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
use SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use Tests\Generator\MySql\Statement\Account\Problem\User\RefusedRenameUserGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserRenamingGenerator;

/**
 * Generates complete requests, including intrinsically contradictory ordered lists.
 *
 * @implements ErisGenerator<RenameUser>
 */
final class RenameUserGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RenameUser> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar and composes each request through Eris.
     */
    public function __construct(GrammarRelease $release)
    {
        $profile = new LanguageProfile($release);
        $requests = \Tests\Generator\Operands::bind(new UserRenamingGenerator(), static fn (UserRenaming $first): ErisGenerator => Generators::map(static fn (array $rest): RenameUser => new RenameUser([$first, ...$rest], $profile), Generators::seq(new UserRenamingGenerator())));
        $this->delegate = Generators::oneOf(
            $requests,
            Generators::map(static fn (RefusedRenameUser $condition): RenameUser => new RenameUser($condition->renamings, $profile), new RefusedRenameUserGenerator()),
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RenameUser>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RenameUser>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<list<UserRenaming>> $renamings The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<RenameUser>
     */
    public static function fromOperands(ErisGenerator $renamings, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RenameUser => new RenameUser(renamings: $parts[0], profile: $parts[1], client: $parts[2]), \Tests\Generator\Operands::tuple($renamings, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<RenameUser>>
     */
    public static function forms(): iterable
    {
        yield 'RenameUser/63' => RenameUserGenerator::fromOperands(
            renamings: \Tests\Generator\Operands::tuple(
                UserRenamingGenerator::fromOperands(
                    from: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    to: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )])
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
