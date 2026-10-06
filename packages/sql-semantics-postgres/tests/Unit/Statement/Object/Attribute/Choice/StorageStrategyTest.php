<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\StorageStrategy;

#[CoversClass(StorageStrategy::class)]
#[Small]
final class StorageStrategyTest extends TestCase
{
    public function testReadIgnoresCase(): void
    {
        self::assertSame([StorageStrategy::Plain, StorageStrategy::External, StorageStrategy::Extended, StorageStrategy::Main, null], [StorageStrategy::read('PLAIN'), StorageStrategy::read('external'), StorageStrategy::read('Extended'), StorageStrategy::read('main'), StorageStrategy::read('toast')]);
    }
}
