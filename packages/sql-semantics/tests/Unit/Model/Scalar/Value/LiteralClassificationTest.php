<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralClassification;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LiteralClassification::class)]
#[Medium]
final class LiteralClassificationTest extends TestCase
{
    #[TestWith([Dialect::MySql, '0x0f', 'binary'])]
    #[TestWith([Dialect::MySql, '0b01', 'bit-string'])]
    #[TestWith([Dialect::PostgreSql, '0x0f', 'number'])]
    #[TestWith([Dialect::PostgreSql, '0b01', 'number'])]
    public function testOfDistinguishesByteStringsFromPostgreSqlIntegerNotation(Dialect $dialect, string $sql, string $kind): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Literal::class, $statement->outputs[0]->expression);
        self::assertSame($kind, $statement->outputs[0]->expression->literalKind->value);
        self::assertSame('SELECT ' . $sql, $statement->toString());
    }

    #[TestWith(['null', null, LiteralKind::Null])]
    #[TestWith(['NULL', null, LiteralKind::Null])]
    #[TestWith(['true', null, LiteralKind::Boolean])]
    #[TestWith(['FALSE', null, LiteralKind::Boolean])]
    #[TestWith(['0x0F', Dialect::MySql, LiteralKind::Binary])]
    #[TestWith(['0B01', Dialect::MySql, LiteralKind::BitString])]
    #[TestWith(['0x0F', null, LiteralKind::Number])]
    #[TestWith(['-1.5e3', null, LiteralKind::Number])]
    #[TestWith(["b'01'", null, LiteralKind::BitString])]
    #[TestWith(["B''", null, LiteralKind::BitString])]
    #[TestWith(["x'0f'", null, LiteralKind::Binary])]
    #[TestWith(["X'AB'", null, LiteralKind::Binary])]
    #[TestWith(["'abc'", Dialect::MySql, LiteralKind::Text])]
    #[TestWith(["'abc'", Dialect::PostgreSql, LiteralKind::Text])]
    public function testOfClassifiesEachSpelling(string $text, ?Dialect $dialect, LiteralKind $kind): void
    {
        self::assertSame($kind, LiteralClassification::of($text, $dialect));
    }

    #[TestWith(['10x0f', Dialect::MySql])]
    #[TestWith(['0x0fz', Dialect::MySql])]
    #[TestWith(["0x0f\n", Dialect::MySql])]
    #[TestWith(['10b01', Dialect::MySql])]
    #[TestWith(['0b012', Dialect::MySql])]
    #[TestWith(["0b01\n", Dialect::MySql])]
    #[TestWith(['a1', Dialect::MySql])]
    #[TestWith(['1a', null])]
    #[TestWith(["1\n", null])]
    #[TestWith(["zb'01'", null])]
    #[TestWith(["b'01'z", null])]
    #[TestWith(["b'01'\n", null])]
    #[TestWith(["zx'0f'", null])]
    #[TestWith(["x'0f'z", null])]
    #[TestWith(["x'0f'\n", null])]
    #[TestWith(["'abc'", null])]
    #[TestWith(['abc', Dialect::MySql])]
    public function testOfRejectsUnclassifiedSpellings(string $text, ?Dialect $dialect): void
    {
        $this->expectException(InvalidStructure::class);
        LiteralClassification::of($text, $dialect);
    }
}
