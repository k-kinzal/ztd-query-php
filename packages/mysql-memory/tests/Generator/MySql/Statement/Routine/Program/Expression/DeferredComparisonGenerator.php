<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredComparison;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredComparison values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredComparison>
 */
final class DeferredComparisonGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredComparison> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredComparison from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): DeferredComparison {
                [$operator, $left, $right] = $parts;
                $precedence = new Precedence();

                return new DeferredComparison(
                    operator: $operator,
                    left: $precedence->fits($left, Precedence::BOOL_PRI, Precedence::BOOL_PRI) ? $left : ($left instanceof Scalar ? new Grouped($left) : ($left instanceof RowOperand ? new GroupedRow($left) : new DeferredGrouped($left))),
                    right: $precedence->opening($right) > Precedence::BOOL_PRI ? $right : ($right instanceof Scalar ? new Grouped($right) : ($right instanceof RowOperand ? new GroupedRow($right) : new DeferredGrouped($right))),
                    scalarInformation: null,
                    inputSource: null,
                );
            },
            Generators::tuple(
                new ComparisonOperatorGenerator($release),
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new RowOperandGenerator($release, $depth - 1),
                    2 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                new DeferredOperandGenerator($release, $depth - 1)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredComparison $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredComparison>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredComparison>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
