<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\ResourceGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceGroup::class)]
#[Small]
final class ResourceGroupTest extends TestCase
{
    public function testVcpusJoinsConsecutiveCpusIntoRanges(): void
    {
        self::assertSame('1-2,4-5,7', (new ResourceGroup('g', false, [1, 2, 4, 5, 7]))->vcpus());
    }

    public function testVcpusWritesEveryCpuOfTheServerAsOneRange(): void
    {
        self::assertSame('0-7', (new ResourceGroup('g', false, range(0, 7)))->vcpus());
    }

    public function testVcpusWritesASingleCpuAlone(): void
    {
        self::assertSame('1', (new ResourceGroup('g', false, [1]))->vcpus());
    }
}
