<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExplainConnection::class)]
#[Medium]
final class ExplainConnectionTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $explain = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('EXPLAIN PARTITIONS FOR CONNECTION 7');
        self::assertInstanceOf(ExplainConnection::class, $explain);
        self::assertSame('partitions', $explain->field(3)->name?->value);
    }

    public function testRenderWritesTheConnection(): void
    {
        self::assertSame('EXPLAIN FORMAT = json FOR CONNECTION 7', (new Semantics(Dialect::MySql))->analyze('explain format = json for connection 7')->toString());
    }

    public function testDeriveStatementRejectsAnOversizedDecimalConnection(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Only integers are allowed');

        (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE FOR CONNECTION 18446744073709551616');
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new ExplainConnection(new Numeral('1'), new Name('JSON'), ExplainModifier::Extended);
    }

    public function testDeriveStatementKeepsTheFuzzerCounterexample(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('DESC INTO @localhost FOR CONNECTION 1');
        self::assertInstanceOf(ExplainConnection::class, $statement);
        self::assertSame('localhost', $statement->into?->name->value);
        self::assertSame(1, $statement->connectionId);
        self::assertSame('DESC INTO @localhost FOR CONNECTION 1', $statement->toString());
        self::assertSame(ExplainVerb::Desc, $statement->verb);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto::class, $statement->diagnostics()[0]);
        self::assertNull($statement->shape());
    }

    public function testDeriveStatementKeepsAnIgnoredEmptyDestination(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("EXPLAIN INTO @'' FOR CONNECTION 0");
        self::assertInstanceOf(ExplainConnection::class, $statement);
        self::assertNull($statement->into);
        self::assertTrue($statement->emptyInto);
        self::assertSame("EXPLAIN INTO @'' FOR CONNECTION 0", $statement->toString());
        self::assertSame([], $statement->diagnostics());
    }

    public function testDeriveStatementRetainsAnInvalidDestination(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("EXPLAIN FORMAT=JSON INTO @'' FOR CONNECTION 0");
        self::assertInstanceOf(ExplainConnection::class, $statement);
        self::assertSame('', $statement->into?->name->value);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget::class, $statement->diagnostics()[0]);
    }


}
