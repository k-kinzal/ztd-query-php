<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(RenameColumn::class)]
#[Small]
final class RenameColumnTest extends TestCase
{
    public function testProfileIdUsesTheSpecifiedRelease(): void
    {
        $target = new TableName(new Identifier('public'), new Identifier('source'));
        $rename = new RenameColumn($target, new Identifier('id'), new Identifier('item'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $rename->profileId());
    }

    public function testToStringKeepsOnlyAndBothIdentifierRoles(): void
    {
        $target = new TableName(new Identifier('app'), new Identifier('source'));
        $rename = new RenameColumn($target, new Identifier('id'), new Identifier('Id'), true, true);

        self::assertSame('ALTER TABLE IF EXISTS ONLY "app"."source" RENAME COLUMN "id" TO "Id"', $rename->toString());
    }
}
