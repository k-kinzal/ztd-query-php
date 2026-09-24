<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ConnectionCharacterSet::class)]
final class ConnectionCharacterSetTest extends TestCase
{
    public function testKeepsTheCharacterSetName(): void
    {
        self::assertSame('latin1', (new ConnectionCharacterSet('latin1'))->characterSet);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConnectionCharacterSet('');
    }
}
