<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\RemainingRows;
use SqlSemantics\Model\Cursor\RowPosition;
use SqlSemantics\Model\Cursor\ScanDirection;
use SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\Model\Statement\Cursor\MoveCursorStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Cursors;

#[CoversClass(Cursors::class)]
#[Medium]
final class CursorsTest extends TestCase
{
    public function testWriteDeclaresEveryCursorOptionFromItsOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DECLARE cur BINARY INSENSITIVE NO SCROLL CURSOR WITH HOLD FOR SELECT 1');
        self::assertInstanceOf(DeclareCursorStatement::class, $statement);
        self::assertSame('DECLARE "cur" BINARY INSENSITIVE NO SCROLL CURSOR WITH HOLD FOR SELECT 1', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(DeclareCursorStatement::class, $rebound);
        self::assertSame('cur', $rebound->name);
        self::assertTrue($rebound->binary);
        self::assertTrue($rebound->hold);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    public function testWriteOmitsDefaultDeclarationOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DECLARE cur CURSOR FOR SELECT 1');
        self::assertSame('DECLARE "cur" CURSOR FOR SELECT 1', $statement->toString());
    }

    #[TestWith(['FETCH BACKWARD ALL FROM cur', 'FETCH BACKWARD ALL FROM "cur"'])]
    #[TestWith(['FETCH ABSOLUTE 3 FROM cur', 'FETCH ABSOLUTE 3 FROM "cur"'])]
    #[TestWith(['FETCH FORWARD 5 FROM cur', 'FETCH FORWARD 5 FROM "cur"'])]
    #[TestWith(['FETCH NEXT FROM cur', 'FETCH NEXT FROM "cur"'])]
    #[TestWith(['FETCH FIRST FROM cur', 'FETCH FIRST FROM "cur"'])]
    public function testWriteFetchesWithEachMovementClass(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(FetchCursorStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(FetchCursorStatement::class, $rebound);
        self::assertSame($statement->movement::class, $rebound->movement::class);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteNormalizesMoveToTheFromSpelling(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('MOVE RELATIVE 2 IN cur');
        self::assertInstanceOf(MoveCursorStatement::class, $statement);
        self::assertSame('MOVE RELATIVE 2 FROM "cur"', $statement->toString());
        self::assertSame('MOVE ABSOLUTE -2 FROM "cur"', $binder->bind('MOVE ABSOLUTE -2 FROM cur')->toString());
    }

    public function testWriteClosesOneOrAllCursors(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('CLOSE "cur"', $binder->bind('CLOSE cur')->toString());
        self::assertSame('CLOSE ALL', $binder->bind('CLOSE ALL')->toString());
    }

    public function testMovementWritesPositionsAndRemainingRowsDirectly(): void
    {
        self::assertSame('PRIOR', Cursors::movement(RowPosition::Prior)->toString());
        self::assertSame('FORWARD ALL', Cursors::movement(new RemainingRows(ScanDirection::Forward))->toString());
    }
}
