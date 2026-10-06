<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Operation;

#[CoversClass(Limit::class)]
#[Medium]
final class LimitTest extends TestCase
{
    public function testRenderKeepsTheCommaSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a from t limit 5, 10');

        self::assertInstanceOf(Select::class, $query->statement);
        $limit = $query->statement->limit;
        self::assertNotNull($limit);
        self::assertTrue($limit->comma);
        self::assertInstanceOf(IntegerLiteral::class, $limit->offset);
        self::assertSame('5', $limit->offset->digits);
        self::assertInstanceOf(IntegerLiteral::class, $limit->count);
        self::assertSame('10', $limit->count->digits);
        self::assertSame('SELECT a FROM t LIMIT 5, 10', $query->toString());
    }

    public function testRenderKeepsTheOffsetSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a from t limit 10 offset 5');

        self::assertInstanceOf(Select::class, $query->statement);
        $limit = $query->statement->limit;
        self::assertNotNull($limit);
        self::assertFalse($limit->comma);
        self::assertInstanceOf(IntegerLiteral::class, $limit->offset);
        self::assertSame('5', $limit->offset->digits);
        self::assertSame('SELECT a FROM t LIMIT 10 OFFSET 5', $query->toString());
    }

    public function testRenderWritesACountAlone(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $limit = new Limit(new IntegerLiteral('3'));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new IntegerLiteral('1'))], null, null, [], null, [], [], $limit));

        self::assertNull($limit->offset);
        self::assertSame('SELECT 1 LIMIT 3', $operation->toString());
    }

    public function testRenderRefusesTheCommaSpellingWithoutAnOffset(): void
    {
        $this->expectExceptionMessage('The comma spelling of LIMIT has an offset.');

        new Limit(new IntegerLiteral('1'), null, true);
    }
}
