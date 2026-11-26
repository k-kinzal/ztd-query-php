<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(XaPrepare::class)]
#[Medium]
final class XaPrepareTest extends TestCase
{
    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame("XA PREPARE 'g', 'b'", (new Inspection())->inspect("xa prepare 'g', 'b'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("XA PREPARE 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementRefusesAFormatAboveTheSignedRangeAfterMySql56(): void
    {
        $statement = new XaPrepare(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808')));
        $accepted = new InspectedStatement((new Inspection('mysql-5.6.51'))->context([]), $statement);

        self::assertSame($statement, $accepted->statement);
        $this->expectExceptionMessage('A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');

        new InspectedStatement((new Inspection())->context([]), new XaPrepare(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808'))));
    }
}
