<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredLike values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredLike>
 */
final class DeferredLikeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredLike> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredLike => new DeferredLike(operand: (new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0]))), pattern: (new Precedence())->admits($parts[1], Precedence::SIMPLE_EXPR) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1]))), escape: $parts[2] === null || (new Precedence())->admits($parts[2], Precedence::SIMPLE_EXPR) ? $parts[2] : ($parts[2] instanceof Scalar ? new Grouped($parts[2]) : ($parts[2] instanceof RowOperand ? new GroupedRow($parts[2]) : new DeferredGrouped($parts[2]))), negated: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => !((new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0])))) instanceof Scalar || !((new Precedence())->admits($parts[1], Precedence::SIMPLE_EXPR) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1])))) instanceof Scalar || (($parts[2] === null || (new Precedence())->admits($parts[2], Precedence::SIMPLE_EXPR) ? $parts[2] : ($parts[2] instanceof Scalar ? new Grouped($parts[2]) : ($parts[2] instanceof RowOperand ? new GroupedRow($parts[2]) : new DeferredGrouped($parts[2])))) !== null && !($parts[2] === null || (new Precedence())->admits($parts[2], Precedence::SIMPLE_EXPR) ? $parts[2] : ($parts[2] instanceof Scalar ? new Grouped($parts[2]) : ($parts[2] instanceof RowOperand ? new GroupedRow($parts[2]) : new DeferredGrouped($parts[2])))) instanceof Scalar),
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
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    })) : Generators::constant(null),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredLike $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredLike>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredLike> $element The generated counterexample
     * @return GeneratedValue<DeferredLike>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
