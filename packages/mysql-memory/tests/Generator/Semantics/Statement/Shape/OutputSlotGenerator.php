<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

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
use SqlSemantics\Statement\Shape\OutputSlot;
use Tests\Generator\Semantics\Statement\Declaration\ColumnGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Reference\Missing\MissingInputGenerator;
use Tests\Generator\Semantics\Statement\Type\NullabilityGenerator;
use Tests\Generator\Semantics\Statement\Type\TypeFactGenerator;

/**
 * Generates OutputSlot values from the generators of its operands.
 *
 * @implements ErisGenerator<OutputSlot>
 */
final class OutputSlotGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OutputSlot> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): OutputSlot => new OutputSlot(name: $parts[0], type: $parts[1], nullability: $parts[2], column: $parts[3], origin: $parts[4], unnamed: $parts[0] === null ? $parts[5] : []),
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                new TypeFactGenerator($release, $depth - 1),
                new NullabilityGenerator($release),
                Generators::oneOf(Generators::constant(null), new ColumnGenerator($release)),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new OutputSlotGenerator($release, $depth - 1)) : Generators::constant(null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new MissingInputGenerator($release)))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OutputSlot $value) use ($release): bool {
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
     * @return GeneratedValue<OutputSlot>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OutputSlot> $element The generated counterexample
     * @return GeneratedValue<OutputSlot>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
