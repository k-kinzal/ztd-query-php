<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates QuantifiedComparison values from the generators of its operands.
 *
 * @implements ErisGenerator<QuantifiedComparison>
 */
final class QuantifiedComparisonGenerator implements ErisGenerator
{
    /** @var ErisGenerator<QuantifiedComparison> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of QuantifiedComparison from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): QuantifiedComparison {
                [$operator, $quantifier, $scalar, $row, $query] = $parts;
                $operand = $quantifier->comparesRows($operator) && $row !== null ? $row : $scalar;
                if (!(new Precedence())->fits($operand, Precedence::BOOL_PRI, Precedence::BOOL_PRI)) {
                    $operand = $operand instanceof Scalar ? new Grouped($operand) : new GroupedRow($operand);
                }

                return new QuantifiedComparison(operand: $operand, operator: $operator, quantifier: $quantifier, query: $query, scalarInformation: null, inputSource: null);
            },
            Generators::suchThat(
                static function (array $parts): bool {
                    [$operator, $quantifier, $scalar, $row, $query] = $parts;
                    $operand = $quantifier->comparesRows($operator) && $row !== null ? $row : $scalar;

                    return (new RowShapes())->query($operand, $query) === null && (new ReadColumns())->problem($query) === null;
                },
                Generators::tuple(
                    Generators::elements(array_values(array_filter(ComparisonOperator::cases(), static fn (ComparisonOperator $operator): bool => $operator !== ComparisonOperator::NullSafeEqual))),
                    new QuantifierGenerator($release),
                    new ScalarGenerator($release, $depth - 1),
                    Generators::oneOf(Generators::constant(null), new RowOperandGenerator($release, $depth - 1)),
                    Generators::suchThat(static fn (Query $query): bool => (new SubqueryOptions())->problem($query) === null, new QueryGenerator($release, $depth - 1))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (QuantifiedComparison $value) use ($release): bool {
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
     * @return GeneratedValueSingle<QuantifiedComparison>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<QuantifiedComparison>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
