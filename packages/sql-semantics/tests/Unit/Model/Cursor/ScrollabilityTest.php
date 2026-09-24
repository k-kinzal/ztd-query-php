<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Scrollability;
use SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Scrollability::class)]
#[Medium]
final class ScrollabilityTest extends TestCase
{
    public function testRepresentsEveryScrollPolicy(): void
    {
        self::assertSame(['', 'SCROLL', 'NO SCROLL'], array_column(Scrollability::cases(), 'value'));
    }

    #[TestWith(['DECLARE cur CURSOR FOR SELECT 1', Scrollability::Default, 'DECLARE "cur" CURSOR FOR SELECT 1'])]
    #[TestWith(['DECLARE cur SCROLL CURSOR FOR SELECT 1', Scrollability::Scroll, 'DECLARE "cur" SCROLL CURSOR FOR SELECT 1'])]
    #[TestWith(['DECLARE cur NO SCROLL CURSOR FOR SELECT 1', Scrollability::ForwardOnly, 'DECLARE "cur" NO SCROLL CURSOR FOR SELECT 1'])]
    public function testClassifiesTheDeclaredScrollPolicy(string $sql, Scrollability $scroll, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(DeclareCursorStatement::class, $statement);
        self::assertSame($scroll, $statement->scroll);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
