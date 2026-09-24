<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ConnectionNames::class)]
final class ConnectionNamesTest extends TestCase
{
    public function testDefaultsToTheServerCharacterSet(): void
    {
        $names = new ConnectionNames();
        self::assertSame([null, null], [$names->characterSet, $names->collation]);
    }

    public function testRejectsAnEmptyCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConnectionNames('utf8mb4', '');
    }
}
