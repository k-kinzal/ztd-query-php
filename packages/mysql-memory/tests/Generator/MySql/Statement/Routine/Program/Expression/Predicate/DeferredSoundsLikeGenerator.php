<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredSoundsLike;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredSoundsLike values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredSoundsLike>
 */
final class DeferredSoundsLikeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredSoundsLike> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredSoundsLike => new DeferredSoundsLike(operand: (new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0]))), pattern: (new Precedence())->admits($parts[1], Precedence::BIT_EXPR) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1]))), scalarInformation: $parts[2], inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => !((new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0])))) instanceof Scalar || !((new Precedence())->admits($parts[1], Precedence::BIT_EXPR) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1])))) instanceof Scalar,
                Generators::tuple(
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
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredSoundsLike $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredSoundsLike>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredSoundsLike> $element The generated counterexample
     * @return GeneratedValue<DeferredSoundsLike>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
