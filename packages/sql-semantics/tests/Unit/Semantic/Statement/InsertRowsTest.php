<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Statement\InsertRows::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertRowsTest extends TestCase
{
    public function testWithRowAndToStringPreserveTheOriginalRows(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $statement);
        $updated = $statement->withRow($statement->rows[0]);
        self::assertCount(1, $statement->rows);
        self::assertSame('INSERT INTO bar (foo) VALUES (1), (1)', $updated->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([1])]
    #[\PHPUnit\Framework\Attributes\TestWith([2])]
    #[\PHPUnit\Framework\Attributes\TestWith([3])]
    public function testAnalyzedRowsHaveTheTargetWidth(int $width): void
    {
        $names = implode(', ', array_map(static fn (int $index): string => 'c' . $index, range(1, $width)));
        $values = implode(', ', range(1, $width));
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (' . $names . ') VALUES (' . $values . '), (' . $values . ')');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $statement);
        self::assertSame([$width, $width], array_map(static fn (\SqlSemantics\Semantic\Statement\ValuesRow $row): int => count($row->values), $statement->rows));
        self::assertSame($width, $statement->target->width);
    }

    public function testToStringRetainsAllInputRows(): void
    {
        self::assertSame('INSERT INTO bar (foo) VALUES (1), (2)', (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1), (2)')->toString());
    }
}
