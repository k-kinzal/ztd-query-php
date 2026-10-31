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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

#[CoversClass(XaCommit::class)]
#[Medium]
final class XaCommitTest extends TestCase
{
    public function testRenderWritesOnePhase(): void
    {
        self::assertSame("XA COMMIT 'g' ONE PHASE", (new Semantics(Dialect::MySql))->analyze("xa commit 'g' one phase")->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA COMMIT 'g'");

        self::assertInstanceOf(XaCommit::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testMySql56RetainsAnUnsignedFormatIdentifier(): void
    {
        $profile = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->profile();
        $statement = new XaCommit(new Xid(new Text('a'), new Text('b'), new Numeral('9223372036854775808')), profile: $profile);
        self::assertSame($profile, $statement->profile());
        self::assertSame("XA COMMIT 'a', 'b', 9223372036854775808", $statement->toString());
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame("XA COMMIT 'g'", (new XaCommit(new Xid(new Text('g'))))->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new XaCommit(new Xid(new Text('g')), profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new XaCommit(new Xid(new Text('g'))))->declarations());
    }
}
