<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Generates TypeFact values from the generators of its operands.
 *
 * @implements ErisGenerator<TypeFact>
 */
final class TypeFactGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TypeFact> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::choose(0, 4), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ChoiceGenerator($release),
                1 => new DependentGenerator($release),
                2 => new InvalidGenerator($release, $depth - 1),
                3 => new KnownGenerator($release),
                4 => new NullOnlyGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ChoiceGenerator($release),
                1 => new DependentGenerator($release),
                2 => new KnownGenerator($release),
                3 => new NullOnlyGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (TypeFact $value) use ($release): bool {
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
     * @return GeneratedValue<TypeFact>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TypeFact> $element The generated counterexample
     * @return GeneratedValue<TypeFact>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
