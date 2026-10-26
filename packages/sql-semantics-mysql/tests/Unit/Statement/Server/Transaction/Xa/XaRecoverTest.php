<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;

#[CoversClass(XaRecover::class)]
#[Medium]
final class XaRecoverTest extends TestCase
{
    public function testRenderWritesConvertXid(): void
    {
        self::assertSame('XA RECOVER CONVERT XID', (new Semantics(Dialect::MySql))->analyze('xa recover convert xid')->toString());
    }

    public function testRenderWritesTheBareForm(): void
    {
        self::assertSame('XA RECOVER', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('xa recover')->toString());
    }

    public function testDeriveStatementRecordsTheRows(): void
    {
        $recover = (new Semantics(Dialect::MySql))->analyze('XA RECOVER');

        self::assertInstanceOf(XaRecover::class, $recover);
        self::assertCount(4, $recover->shape->slots);
        self::assertSame('formatID', $recover->shape->slots[0]->name?->value);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('XA RECOVER', (new XaRecover())->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new XaRecover(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new XaRecover())->declarations());
    }
}
