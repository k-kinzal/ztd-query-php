<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DiagnosticsArea::class)]
#[Medium]
final class DiagnosticsAreaTest extends TestCase
{
    public function testCurrentIsTheDefaultArea(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame('GET CURRENT DIAGNOSTICS @`n` = NUMBER', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('GET DIAGNOSTICS @n = NUMBER')));
        self::assertSame('GET STACKED DIAGNOSTICS @`n` = NUMBER', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('GET STACKED DIAGNOSTICS @n = NUMBER')));
    }
}
