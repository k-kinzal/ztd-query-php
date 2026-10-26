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
        $requests = Generators::bind(new UserRenamingGenerator(), static fn (UserRenaming $first): ErisGenerator => Generators::map(static fn (array $rest): RenameUser => new RenameUser([$first, ...$rest], $profile), Generators::seq(new UserRenamingGenerator())));
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
}
