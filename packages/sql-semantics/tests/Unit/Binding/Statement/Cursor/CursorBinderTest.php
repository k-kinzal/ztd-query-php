<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Cursor\CursorBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CursorBinder::class)]
#[Medium]
final class CursorBinderTest extends TestCase
{
    public function testBindReadsDeclarationOptionsAndTheQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('DECLARE c BINARY INSENSITIVE SCROLL CURSOR WITH HOLD FOR SELECT a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $statement);
        self::assertSame('c', $statement->name);
        self::assertSame(\SqlSemantics\Model\Cursor\Scrollability::Scroll, $statement->scroll);
        self::assertSame(\SqlSemantics\Model\Cursor\Sensitivity::Insensitive, $statement->sensitivity);
        self::assertTrue($statement->binary);
        self::assertTrue($statement->hold);
        self::assertSame('a', $statement->query->resultColumns()[0]->name);
        self::assertSame('DECLARE "c" BINARY INSENSITIVE SCROLL CURSOR WITH HOLD FOR SELECT "a" AS "a" FROM "public"."t"', $statement->toString());
        $forward = $binder->bind('DECLARE c ASENSITIVE NO SCROLL CURSOR WITHOUT HOLD FOR SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $forward);
        self::assertSame(\SqlSemantics\Model\Cursor\Scrollability::ForwardOnly, $forward->scroll);
        self::assertSame(\SqlSemantics\Model\Cursor\Sensitivity::Asensitive, $forward->sensitivity);
        self::assertFalse($forward->hold);
    }

    public function testBindRejectsContradictoryScrollOptions(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::CursorOptions->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DECLARE c SCROLL NO SCROLL CURSOR FOR SELECT 1');
    }

    public function testMovementSeparatesPositionsCountsAndScans(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $next = $binder->bind('FETCH c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $next);
        self::assertSame(\SqlSemantics\Model\Cursor\RowPosition::Next, $next->movement);
        $prior = $binder->bind('FETCH BACKWARD FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $prior);
        self::assertSame(\SqlSemantics\Model\Cursor\RowPosition::Prior, $prior->movement);
        $absolute = $binder->bind('FETCH ABSOLUTE -5 FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $absolute);
        self::assertInstanceOf(\SqlSemantics\Model\Cursor\PositionedRow::class, $absolute->movement);
        self::assertSame(\SqlSemantics\Model\Cursor\OffsetOrigin::Absolute, $absolute->movement->origin);
        self::assertSame('-5', $absolute->movement->offset->text);
        $counted = $binder->bind('FETCH BACKWARD 3 FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $counted);
        self::assertInstanceOf(\SqlSemantics\Model\Cursor\CountedRows::class, $counted->movement);
        self::assertSame(\SqlSemantics\Model\Cursor\ScanDirection::Backward, $counted->movement->direction);
        self::assertSame('3', $counted->movement->count->text);
        $remaining = $binder->bind('MOVE FORWARD ALL IN c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\MoveCursorStatement::class, $remaining);
        self::assertInstanceOf(\SqlSemantics\Model\Cursor\RemainingRows::class, $remaining->movement);
        self::assertSame(\SqlSemantics\Model\Cursor\ScanDirection::Forward, $remaining->movement->direction);
        self::assertSame('MOVE FORWARD ALL FROM "c"', $remaining->toString());
    }

    public function testNameReadsTheCursorOperandForCloseAndFetch(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $close = $binder->bind('CLOSE c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\CloseCursorStatement::class, $close);
        self::assertSame('c', $close->name);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\CloseAllCursorsStatement::class, $binder->bind('CLOSE ALL'));
        $fetch = $binder->bind('FETCH RELATIVE 2 IN c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $fetch);
        self::assertSame('c', $fetch->name);
        self::assertSame('FETCH RELATIVE 2 FROM "c"', $fetch->toString());
    }
}
