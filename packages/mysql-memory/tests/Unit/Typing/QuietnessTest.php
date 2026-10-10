<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Instance;
use MySqlMemory\Typing\Quietness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(Quietness::class)]
#[Small]
final class QuietnessTest extends TestCase
{
    public function testLoudTellsTheStringsThatWarnWhenReadAsANumber(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $statement = $session->analyze("SELECT 'a', _latin1 X'41', @@hostname, VERSION(), IF(1, 'a', 'b'), (SELECT 'a'), CASE WHEN 1 THEN 'a' END, USER(), CONCAT('a'), COALESCE('a'), 'a' COLLATE latin1_bin, @v")->statement;
        self::assertInstanceOf(Select::class, $statement);
        $quietness = new Quietness();

        self::assertSame([true, true, true, true, true, true, true, false, false, false, false, false], array_map(static fn ($item): bool => $item instanceof SelectExpression && $quietness->loud($item->expression), $statement->items));
    }

    public function testBareAnswersTheExpressionInsideParenthesesAndUnaryPlus(): void
    {
        $statement = (new Instance('5.7.44'))->connect()->analyze("SELECT (+('a')), -'a'")->statement;
        self::assertInstanceOf(Select::class, $statement);
        $quietness = new Quietness();

        self::assertSame([StringLiteral::class, Unary::class], array_map(static fn ($item): string => $item instanceof SelectExpression ? $quietness->bare($item->expression)::class : '', $statement->items));
    }

    public function testLoudKeepsTheStringsOf57FunctionsQuiet(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SELECT USER() + 0, CONCAT('1x') = 1, 'abc' + 0");

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'abc'"]], $session->diagnostics->conditions);
    }
}
