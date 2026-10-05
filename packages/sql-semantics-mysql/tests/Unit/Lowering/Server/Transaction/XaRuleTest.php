<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\XaRule;

#[CoversClass(XaRule::class)]
#[Medium]
final class XaRuleTest extends TestCase
{
    public function testStatementLowersEveryXaStatement(): void
    {
        self::assertSame("XA PREPARE 'a'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("xa prepare 'a'")->toString());
    }

    public function testStartLowersBeginAndResume(): void
    {
        self::assertSame("XA START 'a' RESUME", (new Semantics(Dialect::MySql))->analyze("xa begin 'a' resume")->toString());
    }

    public function testEndOptionLowersThe56Migrate(): void
    {
        self::assertSame("XA END 'a' SUSPEND FOR MIGRATE", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("xa end 'a' suspend for migrate")->toString());
    }

    public function testOptionLowersJoin(): void
    {
        self::assertSame("XA START 'a' JOIN", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("xa start 'a' join")->toString());
    }

    public function testFlagLowersOnePhase(): void
    {
        self::assertSame("XA COMMIT 'a' ONE PHASE", (new Semantics(Dialect::MySql))->analyze("xa commit 'a' one phase")->toString());
    }

    public function testXidLowersTheThreeParts(): void
    {
        self::assertSame("XA ROLLBACK 'a', 'b', 7", (new Semantics(Dialect::MySql))->analyze("xa rollback 'a', 'b', 7")->toString());
    }

    public function testLimitsRejectsAFormatAboveTheSignedRange(): void
    {
        self::assertSame("XA COMMIT 'a', 'b', 9223372036854775808", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("XA COMMIT 'a', 'b', 9223372036854775808")->toString());
        self::assertSame("XA COMMIT 'a', 'b', x'8000000000000000'", (new Semantics(Dialect::MySql))->analyze("XA COMMIT 'a', 'b', 0x8000000000000000")->toString());

        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql))->analyze("XA COMMIT 'a', 'b', 9223372036854775808");
    }
}
