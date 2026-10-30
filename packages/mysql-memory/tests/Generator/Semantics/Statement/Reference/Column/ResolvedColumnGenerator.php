<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

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
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use Tests\Generator\Semantics\Statement\RelationGenerator;
use Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator;

/**
 * Generates ResolvedColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<ResolvedColumn>
 */
final class ResolvedColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ResolvedColumn> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ResolvedColumn => new ResolvedColumn(relation: $parts[0], slot: $parts[1], depth: $parts[2], resultReference: $parts[3]),
            Generators::tuple(
                new RelationGenerator($release),
                new OutputSlotGenerator($release, $depth - 1),
                Generators::choose(0, 100),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ResolvedColumn $value) use ($release): bool {
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
     * @return GeneratedValue<ResolvedColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ResolvedColumn> $element The generated counterexample
     * @return GeneratedValue<ResolvedColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
