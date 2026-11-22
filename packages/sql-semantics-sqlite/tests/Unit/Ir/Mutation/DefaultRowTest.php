<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DefaultRow;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(DefaultRow::class)]
#[Small]
final class DefaultRowTest extends TestCase
{
    public function testDefaultInputSuppliesOneRowWithoutAColumnList(): void
    {
        $target = new TableSource(new Identifier('temp'), new Identifier('entries'), []);
        $value = new Insert($target, [], new DefaultRow(), InsertConflict::Ignore);

        self::assertSame('INSERT OR IGNORE INTO "temp"."entries" DEFAULT VALUES', $value->toString());
    }
}
