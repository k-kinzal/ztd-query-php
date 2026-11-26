<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\TableRenameLowering;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TableRenameLowering::class)]
#[Medium]
final class TableRenameLoweringTest extends TestCase
{
    public function testLowerBindsAnEarlierDestinationToItsExternalOrigin(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('daily'));
        $declarations = $semantics->context(['CREATE TABLE daily.incoming (id int)', 'CREATE TABLE daily.stored (id int)']);
        $statement = $semantics->analyze('RENAME TABLE incoming TO archive.mid, archive.mid TO copied', $declarations);

        self::assertInstanceOf(RenameTable::class, $statement);
        self::assertSame('archive', $statement->moves[1]->from->schema->value);
        self::assertSame('incoming', $statement->moves[1]->origin->table->value);
        self::assertSame('daily', $statement->moves[1]->to->schema->value);
    }
}
