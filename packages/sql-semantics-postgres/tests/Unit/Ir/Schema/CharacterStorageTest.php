<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CharacterStorage;

#[CoversClass(CharacterStorage::class)]
#[Small]
final class CharacterStorageTest extends TestCase
{
    public function testTextNamesTheExactBuiltinStorageType(): void
    {
        self::assertSame('pg_catalog.text', CharacterStorage::Text->value);
    }
}
