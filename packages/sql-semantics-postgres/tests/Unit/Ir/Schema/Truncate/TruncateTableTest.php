<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTarget;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(TruncateTable::class)]
#[Small]
final class TruncateTableTest extends TestCase
{
    public function testProfileIdUsesTheSelectedRelease(): void
    {
        $target = new TruncateTarget(new TableName(new Identifier('day'), new Identifier('items')));
        $statement = new TruncateTable([$target], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $statement->profileId());
    }

    public function testToStringPreservesTheOrderOfDuplicateTargets(): void
    {
        $name = new TableName(new Identifier('day'), new Identifier('items'));
        $statement = new TruncateTable([new TruncateTarget($name, true), new TruncateTarget($name)], true, true);

        self::assertSame('TRUNCATE TABLE ONLY "day"."items", "day"."items" RESTART IDENTITY CASCADE', $statement->toString());
    }
}
