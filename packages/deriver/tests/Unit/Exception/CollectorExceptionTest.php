<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Deriver\Sources\Collector;
use Deriver\Exception\CollectorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectorException::class)]
#[UsesClass(Collector::class)]
#[Small]
final class CollectorExceptionTest extends TestCase
{
    public function testReportsAnUnavailableFile(): void
    {
        $this->expectException(CollectorException::class);
        $this->expectExceptionMessage('does-not-exist.php');
        (new Collector())->collect([__DIR__ . '/does-not-exist.php']);
    }
}
