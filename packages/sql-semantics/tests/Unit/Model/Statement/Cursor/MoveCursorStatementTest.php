<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Cursor\MoveCursorStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MoveCursorStatementTest extends TestCase
{
    public function testWithOriginRetainsTheCursorRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('MOVE ABSOLUTE -2 FROM cur');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\MoveCursorStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Cursor\PositionedRow::class, $statement->movement);
        self::assertSame(\SqlSemantics\Model\Cursor\OffsetOrigin::Absolute, $statement->movement->origin);
        self::assertSame('-2', $statement->movement->offset->text);
        $changed = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->toString(), $changed->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\MoveCursorStatement::class, $binder->bind($changed->toString()));
    }
}
