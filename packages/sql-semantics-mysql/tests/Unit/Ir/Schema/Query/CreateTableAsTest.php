<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(CreateTableAs::class)]
#[Small]
final class CreateTableAsTest extends TestCase
{
    public function testToStringKeepsTheLifetimeGuardAndProjectedTypes(): void
    {
        $query = new Select([
            new ResultColumn(new SignedCast(new IntegerLiteral('17')), new Identifier('n')),
            new ResultColumn(new NullLiteral(), new Identifier('absent')),
        ]);

        $target = new TableIdentity(
            new TableName(new Identifier('app'), new Identifier('created')),
            ExternalRelationKind::TemporaryTable
        );
        $value = new CreateTableAs($target, $query, true);

        self::assertSame($query, $value->query);
        self::assertSame(
            'CREATE TEMPORARY TABLE IF NOT EXISTS `app`.`created` ENGINE=InnoDB AS SELECT CAST(17 AS SIGNED) AS `n`, NULL AS `absent`',
            $value->toString()
        );
    }

    public function testToStringCopiesEachOccurrenceOfAResolvedVarcharSlot(): void
    {
        $schema = new Identifier('app');
        $origin = new TableSource($schema, new Identifier('records'), [new Identifier('label')], [new Varchar(8)]);
        $result = new Select([
            new ResultColumn(new TextColumnReference(0), new Identifier('a')),
            new ResultColumn(new TextColumnReference(0), new Identifier('b')),
        ], $origin);
        $target = new TableIdentity(new TableName($schema, new Identifier('copy')), ExternalRelationKind::BaseTable);

        self::assertSame(
            'CREATE TABLE `app`.`copy` ENGINE=InnoDB AS SELECT `__ztd_source`.`label` AS `a`, '
                . '`__ztd_source`.`label` AS `b` FROM `app`.`records` AS `__ztd_source`',
            (new CreateTableAs($target, $result))->toString()
        );
    }

    public function testToStringKeepsDeclaredStorageBeforeTheQuery(): void
    {
        $target = new TableIdentity(
            new TableName(new Identifier('app'), new Identifier('copy')),
            ExternalRelationKind::BaseTable
        );
        $query = new Select([new ResultColumn(new NullLiteral(), new Identifier('n'))]);
        $definition = new TableColumn(new Identifier('N'), IntegerStorage::Tiny, false);
        $value = new CreateTableAs($target, $query, declaredColumns: [$definition]);

        self::assertSame([$definition], $value->declaredColumns);
        self::assertSame('CREATE TABLE `app`.`copy` (`N` TINYINT NOT NULL) ENGINE=InnoDB AS SELECT NULL AS `n`', $value->toString());
    }

    public function testProfileIdUsesTheReleaseOfTheQuery(): void
    {
        $query = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('n'))], release: GrammarRelease::MySql5651);
        $target = new TableIdentity(
            new TableName(new Identifier('app'), new Identifier('created')),
            ExternalRelationKind::TemporaryTable
        );
        $value = new CreateTableAs($target, $query);

        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb', $value->profileId());
    }
}
