<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Pattern\Expression;
use MySqlMemory\Evaluation\Function\Pattern\Replacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Replacement::class)]
#[Small]
final class ReplacementTest extends TestCase
{
    public function testOfReadsGroupsNamesAndEscapes(): void
    {
        $expression = new Expression('/(a)(?<n>b)/u', 2, ['n' => 2]);

        self::assertSame(['[', 1, '-', 2, '-', 2, '-$1 x', 1], Replacement::of('[$1-$2-${n}-\$1 \x$01', $expression)->parts);
    }

    public function testOfTakesAFurtherDigitWhileTheNumberNamesAGroup(): void
    {
        $expression = new Expression('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/u', 11);

        self::assertSame([11, '-', 10, '-', 11, '1'], Replacement::of('$11-$10-$111', $expression)->parts);
    }

    public function testOfRefusesAGroupBeyondThePattern(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Index out of bounds in regular expression search.');

        Replacement::of('[$2]', new Expression('/(b)/u', 1));
    }

    public function testOfRefusesADollarThatNamesNoGroup(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('A capture group has an invalid name.');

        Replacement::of('x$', new Expression('/b/u', 0));
    }

    public function testExpandWritesTheGroupsOfAMatch(): void
    {
        $replacement = Replacement::of('<$2|$1>', new Expression('/(x)?(b)/u', 2));

        self::assertSame('<b|>', $replacement->expand('abc', [[1, 2], null, [1, 2]]));
    }
}
