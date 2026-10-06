<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

#[CoversClass(Xid::class)]
#[Medium]
final class XidTest extends TestCase
{
    public function testRenderWritesThePartsSeparatedByCommas(): void
    {
        self::assertSame("XA START 'g', b'1', x'7fffffffffffffff'", (new Semantics(Dialect::MySql))->analyze("xa start 'g', b'1', 0x7fffffffffffffff")->toString());
    }

    public function testTransactionIsTheFirstPart(): void
    {
        self::assertSame('g', (new Xid(new Text('g')))->transaction->value);
    }
}
