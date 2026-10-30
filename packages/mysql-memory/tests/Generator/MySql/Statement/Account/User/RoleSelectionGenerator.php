<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates RoleSelection values from the generators of its operands.
 *
 * @implements ErisGenerator<RoleSelection>
 */
final class RoleSelectionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RoleSelection> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new RoleSetGenerator($release),
            static fn (RoleSet $set): ErisGenerator => Generators::map(
                static fn (array $roles): RoleSelection => new RoleSelection(set: $set, roles: $roles, inputSource: null),
                match ($set) {
                    RoleSet::Named => Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new AccountNameGenerator($release))),
                    RoleSet::All => Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new AccountNameGenerator($release))),
                    RoleSet::None, RoleSet::Default => Generators::constant([]),
                }
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RoleSelection $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<RoleSelection>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RoleSelection> $element The generated counterexample
     * @return GeneratedValue<RoleSelection>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
