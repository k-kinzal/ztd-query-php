<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(XaCommit::class)]
#[Medium]
final class XaCommitTest extends TestCase
{
    public function testRenderWritesOnePhase(): void
    {
        self::assertSame("XA COMMIT 'g' ONE PHASE", (new Inspection())->inspect("xa commit 'g' one phase")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("XA COMMIT 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementRefusesAFormatAboveTheSignedRangeAfterMySql56(): void
    {
        $statement = new XaCommit(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808')));
        $accepted = new InspectedStatement((new Inspection('mysql-5.6.51'))->context([]), $statement);

        self::assertSame($statement, $accepted->statement);
        $this->expectExceptionMessage('A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');

        new InspectedStatement((new Inspection())->context([]), new XaCommit(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808'))));
    }
}
