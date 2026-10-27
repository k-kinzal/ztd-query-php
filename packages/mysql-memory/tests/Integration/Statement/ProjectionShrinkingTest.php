<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Eris\Quantifier\ForAll;
use Eris\Random\MtRandSource;
use Eris\Random\RandomRange;
use Eris\Shrinker\ShrinkerFactory;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

#[CoversNothing]
#[Large]
final class ProjectionShrinkingTest extends TestCase
{
    public function testShrinkingNestedMeaningReachesTheSmallestExecutableProjection(): void
    {
        $random = new RandomRange(new MtRandSource());
        $random->seed(20261028);
        $quantifier = new ForAll([QueryRootGenerator::projection()], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional counterexample');
        try {
            $quantifier->withMaxSize(10)->then(static function (QueryRoot $statement) use (&$attempts, &$last): void {
                $reply = (new Execution((new Instance('8.4.7'))->connect()))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
                $attempts++;
                $last = $reply->rows;
                self::assertSame([], $reply->rows, 'Intentional counterexample: a scalar projection returns one row.');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame([['val0']], $last);
        }
    }
    public function testShrinkingDependentRowsReachesTheSmallestWidthAndValues(): void
    {
        $random = new RandomRange(new \Eris\Random\MersenneTwister());
        $random->seed(20261029);
        $quantifier = new ForAll([\Tests\Generator\MySql\Statement\Expression\TupleGenerator::comparisons()], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional dependent-row counterexample');
        try {
            $quantifier->withMaxSize(10)->then(static function (QueryRoot $statement) use (&$attempts, &$last): void {
                $expression = $statement->field(0)->expression;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Comparison::class, $expression);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Row::class, $expression->left);
                $last = array_map(static function ($value): string {
                    self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $value);
                    return $value->text;
                }, $expression->left->elements);
                $reply = (new Execution((new Instance('8.4.7'))->connect()))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                self::assertSame([['1']], $reply->rows);
                $attempts++;
                self::fail('Intentional dependent-row counterexample');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame(['0', '0'], $last);
        }
    }

}
