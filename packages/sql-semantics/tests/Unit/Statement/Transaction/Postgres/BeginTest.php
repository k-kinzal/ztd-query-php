<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\Begin;
use SqlSemantics\Statement\Transaction\Postgres\Isolation;

#[CoversClass(Begin::class)]
#[Small]
final class BeginTest extends TestCase
{
    public function testWithIsolationKeepsOriginalCharacteristics(): void
    {
        $original = new Begin(null, true, false);
        $updated = $original->withIsolation(Isolation::Serializable);
        self::assertNull($original->isolation);
        self::assertSame(Isolation::Serializable, $updated->isolation);
        self::assertTrue($updated->readOnly);
        self::assertFalse($updated->deferrable);
    }

    #[TestWith([null, null, null, 'BEGIN'])]
    #[TestWith([Isolation::ReadCommitted, null, null, 'BEGIN ISOLATION LEVEL READ COMMITTED'])]
    #[TestWith([null, false, false, 'BEGIN READ WRITE, NOT DEFERRABLE'])]
    #[TestWith([Isolation::Serializable, true, true, 'BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'])]
    public function testToStringKeepsExplicitFalseOverrides(?Isolation $isolation, ?bool $readOnly, ?bool $deferrable, string $expected): void
    {
        self::assertSame($expected, (new Begin($isolation, $readOnly, $deferrable))->toString());
    }
}
