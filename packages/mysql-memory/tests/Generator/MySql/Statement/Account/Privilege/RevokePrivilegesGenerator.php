<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator;

/**
 * Generates RevokePrivileges values from the generators of its operands.
 *
 * @implements ErisGenerator<RevokePrivileges>
 */
final class RevokePrivilegesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RevokePrivileges> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RevokePrivileges from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RevokePrivileges => new RevokePrivileges(ifExists: $parts[0], privileges: $parts[1], kind: $parts[2], level: $parts[3], users: $parts[4], ignoreUnknownUser: $parts[5], statementInformation: $parts[6], inputSource: $parts[7], tableKeyword: $parts[2] === ObjectKind::Table && $parts[8]),
            Generators::suchThat(
                static fn (array $parts): bool => (($parts[2]) === ObjectKind::Table || array_filter(($parts[1]), static fn ($privilege): bool => $privilege instanceof StaticPrivilege && $privilege->columns !== []) === []) && (array_filter(($parts[1]), static fn ($privilege): bool => $privilege instanceof AllPrivileges) === [] || count(($parts[1])) === 1),
                Generators::tuple(
                    Generators::bool(),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new PrivilegeGenerator($release))),
                    new ObjectKindGenerator($release),
                    new PrivilegeLevelGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::suchThat(static fn (UserSpecification $user): bool => $user->granted() && $user->identification === null, new UserSpecificationGenerator($release)))),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::map(
            static function (RevokePrivileges $statement) use ($release): RevokePrivileges {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof RevokePrivileges, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (RevokePrivileges $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RevokePrivileges>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RevokePrivileges>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
