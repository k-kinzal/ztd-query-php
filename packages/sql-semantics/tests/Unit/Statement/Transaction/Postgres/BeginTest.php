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
    #[TestWith([null, null, null, 'BEGIN'])]
    #[TestWith([Isolation::ReadCommitted, null, null, 'BEGIN ISOLATION LEVEL READ COMMITTED'])]
    #[TestWith([null, false, false, 'BEGIN READ WRITE, NOT DEFERRABLE'])]
    #[TestWith([Isolation::Serializable, true, true, 'BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'])]
    public function testToStringKeepsExplicitFalseOverrides(?Isolation $isolation, ?bool $readOnly, ?bool $deferrable, string $expected): void
    {
        self::assertSame($expected, (new Begin($isolation, $readOnly, $deferrable))->toString());
    }
}
