<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
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
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\Option\TlsRequirementGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator;
use Tests\Generator\MySql\Statement\Account\User\GranteeGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator;

/**
 * Generates GrantPrivileges values from the generators of its operands.
 *
 * @implements ErisGenerator<GrantPrivileges>
 */
final class GrantPrivilegesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<GrantPrivileges> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of GrantPrivileges from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): GrantPrivileges => new GrantPrivileges(privileges: $parts[0], kind: $parts[1], level: $parts[2], grantees: $parts[3], tls: $parts[4], options: $parts[5], as: $parts[6], statementInformation: $parts[7], inputSource: $parts[8], tableKeyword: $parts[1] === ObjectKind::Table && $parts[9]),
            Generators::suchThat(
                static fn (array $parts): bool => (($parts[1]) === ObjectKind::Table || array_filter(($parts[0]), static fn ($privilege): bool => $privilege instanceof StaticPrivilege && $privilege->columns !== []) === []) && (array_filter(($parts[0]), static fn ($privilege): bool => $privilege instanceof AllPrivileges) === [] || count(($parts[0])) === 1)
                && ((($parts[1]) === ObjectKind::Table || array_filter(($parts[0]), static fn ($privilege): bool => $privilege instanceof StaticPrivilege && $privilege->columns !== []) === []) && (array_filter(($parts[0]), static fn ($privilege): bool => $privilege instanceof AllPrivileges) === [] || count(($parts[0])) === 1)),
                Generators::suchThat(static fn (array $parts): bool => ($bit & 3) !== 0 || (array_filter($parts[3], static fn ($grantee): bool => $grantee->identification !== null) === [] && ($parts[4]) === null && count($parts[5]) <= 1 && array_filter($parts[5], static fn ($option): bool => $option instanceof ResourceLimit) === []), Generators::tuple(
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new PrivilegeGenerator($release))),
                    new ObjectKindGenerator($release),
                    new PrivilegeLevelGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new GranteeGenerator($release),
                        1 => Generators::suchThat(static fn (UserSpecification $user): bool => $user->granted() && $user->identification?->credential !== Credential::RandomPassword, new UserSpecificationGenerator($release)),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::oneOf(Generators::constant(null), new TlsRequirementGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new WithOptionGenerator($release))),
                    ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new GrantAsGenerator($release)) : Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool()
                ))
            )
        );
        $this->delegate = Generators::map(
            static function (GrantPrivileges $statement) use ($release): GrantPrivileges {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof GrantPrivileges, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (GrantPrivileges $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0
                    && $value->problem() === null
                    && (new \SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks())->usage($value->privileges, $value->kind, $value->level) === null;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<GrantPrivileges>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GrantPrivileges>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
