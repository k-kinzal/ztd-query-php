<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;

#[CoversClass(IntegerStorage::class)]
#[Small]
final class IntegerStorageTest extends TestCase
{
    public function testIdentifiesAllThreeSignedStorageRoles(): void
    {
        self::assertSame(['SMALLINT', 'INTEGER', 'BIGINT'], array_map(static fn (IntegerStorage $kind): string => $kind->value, IntegerStorage::cases()));
    }
}
