<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\BooleanStorage;

#[CoversClass(BooleanStorage::class)]
#[Small]
final class BooleanStorageTest extends TestCase
{
    public function testIdentifiesNativeBooleanStorage(): void
    {
        self::assertSame('pg_catalog.bool', BooleanStorage::Boolean->value);
    }
}
