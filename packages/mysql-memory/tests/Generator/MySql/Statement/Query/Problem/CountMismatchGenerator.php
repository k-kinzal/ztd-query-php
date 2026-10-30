<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use Tests\Generator\MySql\Statement\Query\ValueRowGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValueRowGenerator;

/**
 * Generates CountMismatch values from the generators of its operands.
 *
 * @implements ErisGenerator<CountMismatch>
 */
final class CountMismatchGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CountMismatch> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CountMismatch => new CountMismatch(list: $parts[0], expected: $parts[0] === CountedList::ValueRows && $parts[4] !== null ? count($parts[4]->values) + 1 : $parts[1], actual: $parts[0] === CountedList::ValueRows && $parts[4] !== null ? count($parts[4]->values) : $parts[2], row: $parts[3], valueRow: $parts[0] === CountedList::ValueRows ? $parts[4] : null),
            Generators::tuple(
                new CountedListGenerator($release),
                Generators::choose(0, 100),
                Generators::choose(0, 100),
                Generators::choose(0, 100),
                Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ValueRowGenerator($release),
                    1 => new DeferredValueRowGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CountMismatch $value) use ($release): bool {
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
     * @return GeneratedValue<CountMismatch>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CountMismatch> $element The generated counterexample
     * @return GeneratedValue<CountMismatch>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
