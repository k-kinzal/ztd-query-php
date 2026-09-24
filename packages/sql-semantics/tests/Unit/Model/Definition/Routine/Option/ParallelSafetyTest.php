<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Option\ParallelSafety;

#[CoversClass(ParallelSafety::class)]
#[Small]
final class ParallelSafetyTest extends TestCase
{
    public function testSpellsEachSafetyAsItsKeyword(): void
    {
        self::assertSame(['SAFE', 'RESTRICTED', 'UNSAFE'], array_column(ParallelSafety::cases(), 'value'));
    }
}
