<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem\Role;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Account\Privilege\RoleGrants;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\GrantedRoleGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates RefusedGrantRoles values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedGrantRoles>
 */
final class RefusedGrantRolesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedGrantRoles> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedGrantRoles from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedGrantRoles => new RefusedGrantRoles(roles: $parts[2] === 0 ? [...$parts[0], new GrantedRole(new AccountName(new Name('')))] : $parts[0], users: match ($parts[2]) {
                0 => $parts[1],
                1 => [...$parts[1], new AccountName(new Name(''))],
                2 => [...$parts[1], $parts[0][0]->role],
                default => throw new LogicException('Unreachable alternative.'),
            }),
            Generators::suchThat(
                static fn (array $parts): bool => (new RoleGrants())->contradictory($parts[2] === 0 ? [...$parts[0], new GrantedRole(new AccountName(new Name('')))] : $parts[0], match ($parts[2]) {
                    0 => $parts[1],
                    1 => [...$parts[1], new AccountName(new Name(''))],
                    2 => [...$parts[1], $parts[0][0]->role],
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::tuple(
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new GrantedRoleGenerator($release))),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new AccountGenerator($release))),
                    Generators::choose(0, 2)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedGrantRoles $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedGrantRoles>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedGrantRoles>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
