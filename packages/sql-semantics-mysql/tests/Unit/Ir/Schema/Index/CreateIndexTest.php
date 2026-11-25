<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexColumn;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(CreateIndex::class)]
#[Small]
final class CreateIndexTest extends TestCase
{
    public function testToStringQuotesTheOwnerAndRetainsTheSelectedKeyOrder(): void
    {
        $table = new TableSource(new Identifier('accounts'), new Identifier('balances'), [new Identifier('entry'), new Identifier('amount')]);
        $index = new CreateIndex($table, new Identifier('exact ` index'), [new IndexColumn(1, true), new IndexColumn(0)], true, false);

        self::assertSame('CREATE UNIQUE INDEX `exact `` index` ON `accounts`.`balances` (`amount` DESC, `entry`) INVISIBLE', $index->toString());
    }

    public function testProfileIdIncludesTheSelectedStorageRelease(): void
    {
        $table = new TableSource(new Identifier('accounts'), new Identifier('balances'), [new Identifier('entry')]);
        $index = new CreateIndex($table, new Identifier('entry lookup'), [new IndexColumn(0)], release: GrammarRelease::MySql5651);

        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/btree-index', $index->profileId());
    }
}
