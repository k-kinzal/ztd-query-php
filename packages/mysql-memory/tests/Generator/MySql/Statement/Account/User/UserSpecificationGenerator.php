<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates UserSpecification values from the generators of its operands.
 *
 * @implements ErisGenerator<UserSpecification>
 */
final class UserSpecificationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<UserSpecification> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of UserSpecification from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static function (array $parts): UserSpecification {
                [$user, $identification, $factors, $initial, $replace, $retainCurrent, $discardOld, $management] = $parts;
                $discardOld = $discardOld && $identification === null;
                $replace = $identification !== null && $identification->replaceable() ? $replace : null;
                $retainCurrent = $retainCurrent && $identification !== null && $identification->retainable();
                $management = $management || $replace !== null || $retainCurrent || $discardOld;
                $factors = $management ? [] : $factors;
                $initial = !$management && $identification?->credential === Credential::None && $factors === [] && $initial !== null && $initial->initial() ? $initial : null;
                if ($user instanceof SessionUser) {
                    $factors = [];
                    $initial = null;
                    if (!$discardOld && !($identification?->password() ?? false)) {
                        $user = new AccountName(new Name($user->alias === null ? 'user' : $user->alias->value));
                    }
                }

                return new UserSpecification(user: $user, identification: $identification, factors: $factors, initial: $initial, replace: $replace, retainCurrent: $retainCurrent, discardOld: $discardOld, inputSource: null);
            },
            Generators::tuple(
                Generators::bind(Generators::bool(), static fn (bool $session): ErisGenerator => $session ? new SessionUserGenerator($release) : new AccountGenerator($release)),
                Generators::oneOf(Generators::constant(null), new IdentificationGenerator($release)),
                Generators::bind(Generators::choose(0, 2), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new IdentificationGenerator($release))),
                Generators::oneOf(Generators::constant(null), new IdentificationGenerator($release)),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                Generators::bool(),
                Generators::bool(),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (UserSpecification $value) use ($release): bool {
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
     * @return GeneratedValueSingle<UserSpecification>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<UserSpecification>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
