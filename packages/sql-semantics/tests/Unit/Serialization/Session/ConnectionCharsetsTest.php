<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Serialization\Session\ConnectionCharsets;

#[CoversClass(ConnectionCharsets::class)]
final class ConnectionCharsetsTest extends TestCase
{
    public function testWriteSpellsDefaultAndOptionalCollation(): void
    {
        self::assertSame('NAMES DEFAULT', ConnectionCharsets::write(new ConnectionNames())->toString());
        self::assertSame('NAMES DEFAULT COLLATE `latin1_bin`', ConnectionCharsets::write(new ConnectionNames(null, 'latin1_bin'))->toString());
        self::assertSame('CHARACTER SET `utf8mb4`', ConnectionCharsets::write(new ConnectionCharacterSet('utf8mb4'))->toString());
    }
}
