<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(RenameSchema::class)]
final class RenameSchemaTest extends TestCase
{
    public function testToStringQuotesBothNameRolesIndependently(): void
    {
        $value = new RenameSchema(new Identifier('old.one'), new Identifier('new."棚"'));

        self::assertSame('ALTER SCHEMA "old.one" RENAME TO "new.""棚"""', $value->toString());
    }

    public function testProfileIdUsesTheGivenRelease(): void
    {
        $value = new RenameSchema(new Identifier('old'), new Identifier('new'), GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
