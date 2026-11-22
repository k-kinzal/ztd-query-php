<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;

#[CoversClass(DropTable::class)]
#[Small]
final class DropTableTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeRelease(): void
    {
        $drop = new DropTable([new TableName(new Identifier('app'), new Identifier('a'))]);

        self::assertStringStartsWith('pg-17.2/', $drop->profileId());
    }

    public function testToStringRetainsTheOrderedTargets(): void
    {
        $schema = new Identifier('app');
        $drop = new DropTable([new TableName($schema, new Identifier('b')), new TableName($schema, new Identifier('a'))], true);

        self::assertSame('DROP TABLE IF EXISTS "app"."b", "app"."a" RESTRICT', $drop->toString());
    }
}
