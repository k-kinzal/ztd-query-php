<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

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
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Shape\FieldGenerator;

/**
 * Generates AmbiguousAlias values from the generators of its operands.
 *
 * @implements ErisGenerator<AmbiguousAlias>
 */
final class AmbiguousAliasGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AmbiguousAlias> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AmbiguousAlias => new AmbiguousAlias(name: $parts[0], candidates: $parts[1]),
            Generators::tuple(
                new NameGenerator($release),
                Generators::bind(Generators::choose(2, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new FieldGenerator($release, $depth - 1)))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AmbiguousAlias $value) use ($release): bool {
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
     * @return GeneratedValue<AmbiguousAlias>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AmbiguousAlias> $element The generated counterexample
     * @return GeneratedValue<AmbiguousAlias>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
