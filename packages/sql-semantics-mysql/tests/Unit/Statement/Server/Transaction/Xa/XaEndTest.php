<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Statement\Operation;

#[CoversClass(XaEnd::class)]
#[Medium]
final class XaEndTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("XA END 'g' SUSPEND", (new Semantics(Dialect::MySql))->analyze("xa end 'g' suspend")->toString());
    }

    public function testRenderWritesForMigrateOf56(): void
    {
        self::assertSame("XA END 'g' SUSPEND FOR MIGRATE", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("xa end 'g' suspend for migrate")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA END 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementRefusesAFormatAboveTheSignedRangeAfterMySql56(): void
    {
        $statement = new XaEnd(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808')));
        $accepted = new Operation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), $statement);

        self::assertSame($statement, $accepted->statement);
        $this->expectExceptionMessage('A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');

        new Operation((new Semantics(Dialect::MySql))->context([]), new XaEnd(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808'))));
    }
}
