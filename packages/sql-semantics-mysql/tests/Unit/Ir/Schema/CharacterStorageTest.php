<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage;

#[CoversClass(CharacterStorage::class)]
#[Small]
final class CharacterStorageTest extends TestCase
{
    public function testToStringKeepsTheNativeCollationAndLength(): void
    {
        self::assertSame('VARCHAR(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', (new CharacterStorage(0))->toString());
    }
}
