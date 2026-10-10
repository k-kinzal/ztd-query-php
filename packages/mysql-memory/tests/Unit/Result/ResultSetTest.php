<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ResultSet::class)]
#[Small]
final class ResultSetTest extends TestCase
{
    public function testWarningsAnswersTheWarningCount(): void
    {
        $column = new ResultColumn('a', Field::Long, 11, 0, 0, 63);
        $result = new ResultSet([$column], [['1'], [null]], 4);

        self::assertSame(4, $result->warnings());
        self::assertSame([$column], $result->columns);
        self::assertSame([['1'], [null]], $result->rows);
    }

    public function testWarningsIsZeroByDefault(): void
    {
        $result = new ResultSet([], []);

        self::assertSame(0, $result->warnings());
    }

    public function testWarningsOfAQueryWithoutWarnings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 + 1 AS two, NULL AS nothing')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(0, $result->warnings());
        self::assertSame([['2', null]], $result->rows);
        self::assertSame('two', $result->columns[0]->name);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
        self::assertSame('nothing', $result->columns[1]->name);
        self::assertSame(Field::Null, $result->columns[1]->type);
    }
}
