<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Pattern\Expression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Expression::class)]
#[Small]
final class ExpressionTest extends TestCase
{
    public function testFindAnswersTheSpansOfTheMatchAndItsGroups(): void
    {
        $expression = new Expression('/(x)?(b+)/u', 2);

        self::assertSame([[[1, 3], null, [1, 3]], null], [$expression->find('abbc', 0), $expression->find('abbc', 3)]);
    }

    public function testFindFailsWhenTheSearchBacktracksTooMuch(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Timeout exceeded in regular expression match.');

        (new Expression('/(?:\D+|<\d+>)*[!?]/u', 0))->find(str_repeat('foobar foobar foobar', 1000), 0);
    }

    public function testAllStepsOneCharacterFurtherAfterAnEmptyMatch(): void
    {
        $spans = array_map(static fn (array $match): ?array => $match[0], (new Expression('/x*|b/u', 0))->all('aé', 0));

        self::assertSame([[0, 0], [1, 1], [3, 3]], $spans);
    }

    public function testAllStopsAtTheLimitAndStartsAtTheOffset(): void
    {
        $spans = array_map(static fn (array $match): ?array => $match[0], (new Expression('/b/u', 0))->all('abcabcabc', 2, 2));

        self::assertSame([[4, 5], [7, 8]], $spans);
    }
}
