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

    public function testDeriveStatementRetainsAnOversizedDecimalConnection(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FOR CONNECTION 18446744073709551616');
        self::assertInstanceOf(ExplainConnection::class, $statement);
        self::assertNull($statement->connectionId);
        self::assertSame('18446744073709551616', $statement->connection->text);
        self::assertSame('EXPLAIN FOR CONNECTION 18446744073709551616', $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber::class, $statement->diagnostics()[0]);
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new ExplainConnection(new Numeral('1'), new Name('JSON'), ExplainModifier::Extended);
    }

    public function testDeriveStatementIsNeverReachedForTheFuzzerCounterexample(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('DESC INTO @localhost FOR CONNECTION 1');
            self::fail('EXPLAIN FOR CONNECTION takes no INTO.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain::class, $failure->problem);
            self::assertSame(20, $failure->end);
            self::assertTrue($failure->aborts);
        }
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

    public function testDeriveStatementIsNeverReachedForAnInvalidDestination(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("EXPLAIN FORMAT=JSON INTO @'' FOR CONNECTION 0");
            self::fail('The empty variable name is refused before the FOR CONNECTION clause.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget::class, $failure->problem);
        }
    }


}
