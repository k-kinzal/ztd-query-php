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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Statement\Operation;

#[CoversClass(XaPrepare::class)]
#[Medium]
final class XaPrepareTest extends TestCase
{
    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame("XA PREPARE 'g', 'b'", (new Semantics(Dialect::MySql))->analyze("xa prepare 'g', 'b'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA PREPARE 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementRefusesAFormatAboveTheSignedRangeAfterMySql56(): void
    {
        $statement = new XaPrepare(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808')));
        $accepted = new Operation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), $statement);

        self::assertSame($statement, $accepted->statement);
        $this->expectExceptionMessage('A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');

        new Operation((new Semantics(Dialect::MySql))->context([]), new XaPrepare(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808'))));
    }
}
