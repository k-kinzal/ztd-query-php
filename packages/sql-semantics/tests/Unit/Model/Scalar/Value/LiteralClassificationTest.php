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
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Value\LiteralClassification::class)]
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

}
