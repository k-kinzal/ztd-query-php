<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Composition;

/**
 * Composes Aggregate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate>
 */
final class AggregateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate> */
    use Composition;

    /**
     * Composes a two-row population and both spellings of its statistic.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, float}>
     */
    public static function populations(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::bind(Generators::tuple(new AggregateAliasGenerator(), Generators::choose(0, 100), Generators::choose(0, 100)), static function (array $parts) use ($release): ErisGenerator {
            [$alias, $first, $second] = $parts;
            $calls = new self(Generators::constant($alias->function()), Generators::vector(1, new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(Generators::constant(new Name('value')))), all: Generators::bool(), alias: Generators::constant($alias));
            $queries = Generators::map(static function (M\Call\Aggregate\Aggregate $call) use ($first, $second): M\QueryRoot {
                $rows = new M\Query\Set\SetOperation(
                    new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) $first), new Name('value'))]),
                    M\Query\Set\SetOperator::Union,
                    M\Query\Set\SetQuantifier::All,
                    new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral((string) $second), new Name('value'))]),
                );
                $canonical = new M\Call\Aggregate\Aggregate($call->function, [new M\Name\ColumnUse(new Name('value'))]);
                return new M\QueryRoot(new M\Query\Select([], [
                    new M\Query\SelectExpression($call, new Name('selected')),
                    new M\Query\SelectExpression($canonical, new Name('canonical')),
                ], from: new M\Relation\DerivedTable($rows, new Name('population'))));
            }, $calls);
            $deviation = abs($first - $second) / 2.0;
            $expected = $alias->function() === M\Call\Aggregate\AggregateFunction::Variance ? $deviation * $deviation : $deviation;
            return Generators::tuple(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $queries), Generators::constant($expected));
        });
    }

    /**
     * @param ErisGenerator<M\Call\Aggregate\AggregateFunction> $function Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>> $arguments Generated constructor operand
     * @param ErisGenerator<bool>|null $distinct Generated constructor operand
     * @param ErisGenerator<bool>|null $all Generated constructor operand
     * @param ErisGenerator<Name|M\Call\WindowSpecification|null>|null $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<int>|null $divisionPrecision The calculation precision for bound averages
     * @param ErisGenerator<M\Call\Aggregate\AggregateAlias|null>|null $alias Generated keyword synonym
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $distinct = null, ?ErisGenerator $all = null, ?ErisGenerator $over = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null, ?ErisGenerator $divisionPrecision = null)
    {
        $this->delegate = Generators::bind($function, static function (M\Call\Aggregate\AggregateFunction $selected) use ($arguments, $distinct, $all, $over, $scalarInformation, $inputSource, $alias, $divisionPrecision): ErisGenerator {
            return Generators::map(static function (array $parts): M\Call\Aggregate\Aggregate {
                return new M\Call\Aggregate\Aggregate(function: $parts[0], arguments: $parts[1], distinct: $parts[2], all: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6], alias: $parts[7], divisionPrecision: $parts[5] !== null && $parts[0] === M\Call\Aggregate\AggregateFunction::Average ? $parts[8] : null);
            }, Generators::tuple(
                Generators::constant($selected),
                $arguments,
                $distinct ?? Generators::constant(false),
                $all ?? Generators::constant(false),
                $over ?? Generators::constant(null),
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? AggregateAliasGenerator::forFunction($selected),
                $divisionPrecision ?? Generators::constant(4)
            ));
        });
    }
}
