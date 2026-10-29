<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Operator;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Construction\Occurrences;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes Arithmetic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic>
 */
final class ArithmeticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic> */
    use Composition;

    /**
     * @param ErisGenerator<M\Expression\Operator\ArithmeticOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<int>|null $divisionPrecision The generated calculation precision for bound divisions
     * @param ErisGenerator<bool>|null $modWord The generated written alternative
     */
    public function __construct(ErisGenerator $operator, ErisGenerator $left, ErisGenerator $right, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $modWord = null, ?ErisGenerator $divisionPrecision = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Expression\Operator\Arithmetic {
            return new M\Expression\Operator\Arithmetic(operator: $parts[0], left: $parts[1], right: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], modWord: $parts[5] && $parts[0] === M\Expression\Operator\ArithmeticOperator::Modulo, divisionPrecision: $parts[3] !== null && $parts[0] === M\Expression\Operator\ArithmeticOperator::Divide ? $parts[6] : null);
        }, \Tests\Generator\Operands::tuple(
            $operator,
            $left,
            $right,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $modWord ?? Generators::bool(),
            $divisionPrecision ?? Generators::choose(0, 30)
        ));
    }

    /**
     * Composes divisions, numeric consumers and text consumers before publication.
     *
     * @return ErisGenerator<array{M\QueryRoot, list<string>}>
     */
    public static function precisionQueries(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(self::precisionSample(...), \Tests\Generator\Operands::tuple(Generators::constant($release), Generators::choose(-999, 999), Generators::choose(2, 19), Generators::choose(0, 12), Generators::bool()));
    }

    /**
     * Keeps the generated calculation operands, branch choice and expected rows related while shrinking.
     *
     * @param array{GrammarRelease, int, int, int, bool} $parts The release, dividend, divisor, increment and chosen branch
     * @return array{M\QueryRoot, list<string>}
     */
    public static function precisionSample(array $parts): array
    {
        [$release, $dividend, $divisor, $precision, $chosen] = $parts;
        $left = new M\Literal\NumberLiteral((string) abs($dividend));
        $left = $dividend < 0 ? new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, $left) : $left;
        $right = new M\Literal\NumberLiteral((string) $divisor);
        $division = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Divide, $left, $right);
        $grouped = new M\Expression\Grouped($division);
        $case = new M\Expression\Branching\CaseExpression(null, [new M\Expression\Branching\CaseBranch(new M\Literal\NumberLiteral($chosen ? '1' : '0'), $division)], new M\Literal\NumberLiteral('0.1'));
        $product = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, $grouped, Occurrences::fresh($right));
        $caseProduct = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, new M\Expression\Grouped($case), Occurrences::fresh($right));
        $target = new M\Type\CastTarget(M\Type\Kind\CastKind::Decimal, '30', '20');
        $workingScale = intdiv($precision + 8, 9) * 9;
        $raw = bcdiv((string) $dividend, (string) $divisor, $workingScale);
        $round = self::rounded(...);
        $shown = $round($raw, $precision);
        $rounded = new M\Literal\NumberLiteral(ltrim($shown, '-'));
        $rounded = str_starts_with($shown, '-') ? new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, $rounded) : $rounded;
        $scalar = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($division, new \SqlSemantics\Statement\Identifier\Name('fraction'))]));
        $expressions = [$division, $product, new M\Expression\Conversion\Cast($division, $target), $case, $caseProduct, new M\Expression\Conversion\Cast($case, $target), new M\Expression\Comparison(M\Expression\ComparisonOperator::Equal, $division, $rounded), new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, $scalar, Occurrences::fresh($right))];
        $sum = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [Occurrences::fresh($division)]);
        $average = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Average, [Occurrences::fresh($division)]);
        $least = new M\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('LEAST'), [new M\Call\CallArgument(Occurrences::fresh($division)), new M\Call\CallArgument(new M\Literal\NumberLiteral('9999.0'))]);
        $expressions[] = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, $sum, Occurrences::fresh($right));
        $expressions[] = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, Occurrences::fresh($average), Occurrences::fresh($right));
        $expressions[] = new M\Expression\Conversion\Cast($average, Occurrences::fresh($target));
        $expressions[] = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, $least, Occurrences::fresh($right));
        $items = [];
        foreach ($expressions as $index => $expression) {
            $items[] = new M\Query\SelectExpression(Occurrences::fresh($expression), new \SqlSemantics\Statement\Identifier\Name('value_' . $index));
        }
        $session = (new \MySqlMemory\Instance(substr($release->value, 6)))->connect();
        $setting = $session->instance->catalog->find('div_precision_increment');
        \SqlSemantics\Diagnostic\Check::invariant($setting !== null, 'Exact division has a session precision increment.');
        $session->variables->set($setting, (string) $precision);
        $statement = (new \MySqlMemory\Session\Statement\Binding())->statement($session, new M\QueryRoot(new M\Query\Select([], $items)));
        \SqlSemantics\Diagnostic\Check::invariant($statement instanceof M\QueryRoot, 'Publication retains the generated query.');
        $multiplied = bcmul($raw, (string) $divisor, $workingScale);
        $branch = $chosen ? $raw : '0.1';
        $leastNumber = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? $round($raw, max($precision, 1)) : $raw;
        return [$statement, [$shown, $round($multiplied, $precision), $round($raw, 20), $chosen ? $shown : '0.1', $round(bcmul($branch, (string) $divisor, max($workingScale, 1)), max($precision, 1)), $round($branch, 20), bccomp($raw, $shown, max($workingScale, $precision)) === 0 ? '1' : '0', $round($multiplied, $precision), $round($multiplied, $precision), $round($multiplied, min(30, $precision * 2)), $round($raw, 20), $round(bcmul($leastNumber, (string) $divisor, max($workingScale, $precision, 1)), max($precision, 1))]];
    }
    /**
     * Rounds the independent arithmetic expectation half away from zero.
     *
     * @param numeric-string $value The calculated expectation before display rounding
     * @return numeric-string
     */
    public static function rounded(string $value, int $scale): string
    {
        return bcadd($value, bcdiv(str_starts_with($value, '-') ? '-5' : '5', bcpow('10', (string) ($scale + 1)), $scale + 1), $scale);
    }
}
