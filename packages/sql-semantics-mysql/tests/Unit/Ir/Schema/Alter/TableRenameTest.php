<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\TableRename;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(TableRename::class)]
#[Small]
final class TableRenameTest extends TestCase
{
    public function testToStringKeepsTheCurrentAndDestinationNamesInOrder(): void
    {
        $origin = new TableName(new Identifier('current'), new Identifier('first'));
        $target = new TableName(new Identifier('archive'), new Identifier('with`tick'));
        $move = new TableRename($origin, $target, $origin);

        self::assertSame('`current`.`first` TO `archive`.`with``tick`', $move->toString());
    }
}
