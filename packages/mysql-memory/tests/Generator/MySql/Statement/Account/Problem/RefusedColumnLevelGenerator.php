<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\DatabaseLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\GlobalLevelGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates RefusedColumnLevel values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedColumnLevel>
 */
final class RefusedColumnLevelGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedColumnLevel> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedColumnLevel from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedColumnLevel => new RefusedColumnLevel(privilege: $parts[0], level: $parts[1], program: $parts[2]),
            Generators::tuple(
                Generators::suchThat(static fn (StaticPrivilege $privilege): bool => $privilege->columns !== [], new StaticPrivilegeGenerator($release)),
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new CurrentDatabaseLevelGenerator($release),
                    1 => new DatabaseLevelGenerator($release),
                    2 => new GlobalLevelGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::oneOf(Generators::constant(null), new QualifiedNameGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedColumnLevel $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedColumnLevel>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedColumnLevel>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
