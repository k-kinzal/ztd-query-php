<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;

#[CoversClass(XaCommit::class)]
#[Medium]
final class XaCommitTest extends TestCase
{
    public function testRenderWritesOnePhase(): void
    {
        self::assertSame("XA COMMIT 'g' ONE PHASE", (new Semantics(Dialect::MySql))->analyze("xa commit 'g' one phase")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA COMMIT 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
