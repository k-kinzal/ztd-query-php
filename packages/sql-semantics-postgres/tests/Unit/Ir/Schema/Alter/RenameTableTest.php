<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(RenameTable::class)]
#[Small]
final class RenameTableTest extends TestCase
{
    public function testProfileIdUsesTheSpecifiedRelease(): void
    {
        $target = new TableName(new Identifier('public'), new Identifier('source'));
        $rename = new RenameTable($target, new Identifier('moved'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $rename->profileId());
    }

    public function testToStringQuotesTheDestinationWithinTheSameSchema(): void
    {
        $target = new TableName(new Identifier('app'), new Identifier('source'));
        $rename = new RenameTable($target, new Identifier('named "table"'), true);

        self::assertSame('ALTER TABLE IF EXISTS "app"."source" RENAME TO "named ""table"""', $rename->toString());
    }
}
