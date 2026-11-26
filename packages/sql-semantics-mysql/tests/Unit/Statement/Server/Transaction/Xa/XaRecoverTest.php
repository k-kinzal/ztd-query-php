<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(XaRecover::class)]
#[Medium]
final class XaRecoverTest extends TestCase
{
    public function testRenderWritesConvertXid(): void
    {
        self::assertSame('XA RECOVER CONVERT XID', (new Inspection())->inspect('xa recover convert xid')->toString());
    }

    public function testRenderWritesTheBareForm(): void
    {
        self::assertSame('XA RECOVER', (new Inspection('mysql-5.6.51'))->inspect('xa recover')->toString());
    }

    public function testDeriveStatementRecordsTheRows(): void
    {
        $recover = (new Inspection())->inspect('XA RECOVER');

        self::assertSame(4, $recover->fields()?->count());
        self::assertSame('formatID', $recover->field(0)->slot->name?->value);
    }
}
