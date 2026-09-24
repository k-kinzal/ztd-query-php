<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\SystemSettings;

#[CoversClass(SystemSettings::class)]
#[Medium]
final class SystemSettingsTest extends TestCase
{
    #[TestWith(["ALTER SYSTEM SET work_mem TO '1MB'", 'ALTER SYSTEM SET "work_mem" = \'1MB\''])]
    #[TestWith(['ALTER SYSTEM RESET a.b', 'ALTER SYSTEM RESET "a"."b"'])]
    #[TestWith(['ALTER SYSTEM RESET ALL', 'ALTER SYSTEM RESET ALL'])]
    public function testWriteUsesTheCanonicalSpelling(string $sql, string $expected): void
    {
        self::assertSame($expected, SystemSettings::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(SystemSettings::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('RESET ALL')));
    }
}
