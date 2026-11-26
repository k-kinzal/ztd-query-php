<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;

#[CoversClass(ChangeSchemaOwner::class)]
final class ChangeSchemaOwnerTest extends TestCase
{
    public function testToStringPreservesTheSelectedSessionAuthorization(): void
    {
        $value = new ChangeSchemaOwner(new Identifier('archive'), SchemaSessionRole::Session);

        self::assertSame('ALTER SCHEMA "archive" OWNER TO SESSION_USER', $value->toString());
    }

    public function testProfileIdRetainsTheNativePostgreSqlDefault(): void
    {
        $value = new ChangeSchemaOwner(new Identifier('archive'), new Identifier('app_role'));

        self::assertSame('pg-17.2/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
