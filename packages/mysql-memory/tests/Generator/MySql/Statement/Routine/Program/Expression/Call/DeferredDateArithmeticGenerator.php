<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Call\Keyword\DateArithmeticAliasGenerator;
use Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredDateArithmetic values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredDateArithmetic>
 */
final class DeferredDateArithmeticGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredDateArithmetic> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredDateArithmetic => new DeferredDateArithmetic(subtract: $parts[0], date: $parts[1], quantity: $parts[2], unit: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5], alias: $parts[6] !== null && $parts[6]->subtracts() === $parts[0] ? $parts[6] : null),
            Generators::suchThat(
                static fn (array $parts): bool => (!($parts[1]) instanceof Scalar || !($parts[2]) instanceof Scalar),
                Generators::tuple(
                    Generators::bool(),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    new IntervalUnitGenerator($release),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new DateArithmeticAliasGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredDateArithmetic $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredDateArithmetic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredDateArithmetic> $element The generated counterexample
     * @return GeneratedValue<DeferredDateArithmetic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
