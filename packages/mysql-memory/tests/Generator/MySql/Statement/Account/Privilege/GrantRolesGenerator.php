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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\GrantedRoleGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator;
use Tests\Generator\Semantics\Contract\LanguageProfileGenerator;

/**
 * Generates GrantRoles values from the generators of its operands.
 *
 * @implements ErisGenerator<GrantRoles>
 */
final class GrantRolesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<GrantRoles> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of GrantRoles from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): GrantRoles => new GrantRoles(roles: $parts[0], users: $parts[1], withAdminOption: $parts[2], profile: $parts[3], client: $parts[4], source: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new GrantedRoleGenerator($release))),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new AccountGenerator($release))),
                Generators::bool(),
                new LanguageProfileGenerator($release),
                Generators::oneOf(Generators::constant(null), new CharsetGenerator($release)),
                Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (GrantRoles $statement) use ($release): GrantRoles {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof GrantRoles, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (GrantRoles $value) use ($release): bool {
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
     * @return GeneratedValueSingle<GrantRoles>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GrantRoles>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
