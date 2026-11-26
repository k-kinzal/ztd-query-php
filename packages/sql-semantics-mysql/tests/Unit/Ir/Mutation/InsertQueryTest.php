<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertQuery;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(InsertQuery::class)]
#[Small]
final class InsertQueryTest extends TestCase
{
    public function testToStringKeepsTheOwnedQuery(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id')]);
        $select = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('n'))]);
        $insert = new InsertQuery($target, $select);

        self::assertSame('INSERT INTO `app`.`events` (`id`) ' . $select->toString(), $insert->toString());
    }

    public function testProfileIdUsesTheSourceProfile(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id')]);
        $select = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('n'))]);

        self::assertSame($select->profileId(), (new InsertQuery($target, $select))->profileId());
    }

    public function testMatchesNativeDestinationTypesInOrder(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('name'), new Identifier('id')], [new Varchar(8), ColumnKind::SignedInt]);
        $select = new Select([
            new ResultColumn(new TextLiteral('a '), new Identifier('label')),
            new ResultColumn(new IntegerLiteral('7'), new Identifier('number')),
        ]);

        self::assertSame("INSERT INTO `app`.`events` (`name`, `id`) SELECT 'a ' AS `label`, 7 AS `number`", (new InsertQuery($target, $select))->toString());
    }

    public function testRequiresATypeWithDefinedInsertAssignmentSemantics(): void
    {
        $target = new TableSource(
            new Identifier('app'),
            new Identifier('t'),
            [new Identifier('n')],
            [new IntegralSlot(IntegerStorage::Tiny, true)]
        );
        $query = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('n'))]);
        $this->expectExceptionMessage('An INSERT destination requires a writable signed INT or native VARCHAR slot.');

        new InsertQuery($target, $query);
    }
}
