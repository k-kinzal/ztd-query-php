<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Combine;

use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetOperation::class)]
#[Small]
final class SetOperationTest extends TestCase
{
    public function testWidthIsTheNumberOfCombinedColumns(): void
    {
        self::assertSame(2, (new SetOperation(SetKind::Except, false, new ZeroRows(3), new ZeroRows(2), [Domain::integer(), Domain::integer()]))->width());
    }
}
