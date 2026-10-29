<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes QuantifiedComparison values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison>
 */
final class QuantifiedComparisonGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison> */
    use Composition;

    /**
     * Composes row membership against generated two-row or empty subqueries.
     * The shared row shape, comparison operator and expected result survive shrinking together.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, string|null}>
     */
    public static function rowQueries(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::bind(Generators::tuple(
            Generators::choose(0, 10),
            Generators::choose(0, 10),
            Generators::choose(0, 10),
            Generators::bool(),
            Generators::bool(),
            new QuantifierGenerator()
        ), static function (array $parts) use ($release): ErisGenerator {
            [$first, $second, $chosen, $empty, $nullable, $quantifier] = $parts;
            $all = $quantifier === M\Expression\Subquery\Quantifier::All;
            $matched = $chosen === $second || $chosen === $second + 1;
            $expected = $empty ? ($all ? '1' : '0') : ($nullable && $matched ? null : (($all !== $matched) ? '1' : '0'));
            $operand = new M\Expression\Row([
                $nullable ? new M\Literal\NullLiteral() : new M\Literal\NumberLiteral((string) $first),
                new M\Literal\NumberLiteral((string) $chosen),
            ]);
            $query = new M\Query\Set\SetOperation(
                new M\Query\Select([], [
                    new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) $first)),
                    new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) $second)),
                ], where: $empty ? new M\Literal\NumberLiteral('0') : null),
                M\Query\Set\SetOperator::Union,
                M\Query\Set\SetQuantifier::All,
                new M\Query\Select([], [
                    new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) $first)),
                    new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) ($second + 1))),
                ], where: $empty ? new M\Literal\NumberLiteral('0') : null),
            );
            $comparisons = new self(
                Generators::constant($operand),
                Generators::constant($all ? M\Expression\ComparisonOperator::NotEqual : M\Expression\ComparisonOperator::Equal),
                Generators::constant($quantifier),
                Generators::constant($query),
            );
            $queries = Generators::map(static fn (M\Expression\Subquery\QuantifiedComparison $comparison): M\QueryRoot => new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($comparison, new \SqlSemantics\Statement\Identifier\Name('chosen'))])), $comparisons);
            return Generators::tuple(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $queries), Generators::constant($expected));
        });
    }

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|M\Expression\RowOperand> $operand Generated constructor operand
     * @param ErisGenerator<M\Expression\ComparisonOperator> $operator Generated constructor operand
     * @param ErisGenerator<M\Expression\Subquery\Quantifier> $quantifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $operator, ErisGenerator $quantifier, ErisGenerator $query, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Expression\Subquery\QuantifiedComparison {
            return new M\Expression\Subquery\QuantifiedComparison(operand: $parts[0], operator: $parts[1], quantifier: $parts[2], query: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $operand,
            $operator,
            $quantifier,
            $query,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
