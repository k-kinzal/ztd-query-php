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
     * Generates complete RenameUser values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<RenameUser>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No RenameUser forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): RenameUser {
            if (!$statement instanceof RenameUser) {
                throw new LogicException('Publishing RenameUser must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar and composes each request through Eris.
     * @return ErisGenerator<RenameUser>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        $profile = new LanguageProfile($release);
        $requests = Generators::bind(new UserRenamingGenerator(), static fn (UserRenaming $first): ErisGenerator => Generators::map(static fn (array $rest): RenameUser => new RenameUser([$first, ...$rest], $profile), Generators::seq(new UserRenamingGenerator())));
        return Generators::oneOf($requests, Generators::map(static fn (RefusedRenameUser $condition): RenameUser => new RenameUser($condition->renamings, $profile), new RefusedRenameUserGenerator()));
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
        return Generators::map(static fn (array $parts): RenameUser => new RenameUser(renamings: $parts[0], profile: $parts[1], client: $parts[2]), Generators::tuple($renamings, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<RenameUser>>
     */
    public static function forms(): iterable
    {
        yield 'account operands' => self::operands(GrammarRelease::MySql847);
    }

}
