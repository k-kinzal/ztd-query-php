<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlLoadStatements;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Rewrite\MySqlLoadDataInsert;

#[CoversClass(MySqlLoadDataInsert::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
final class MySqlLoadDataInsertTest extends TestCase
{
    public function testSqlForWritesTheRowsAsAnInsert(): void
    {
        self::assertSame(
            'INSERT INTO `t` (`id`, `name`) VALUES (1, \'a\')',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['id', 'name'],
                [],
                [['id' => '1', 'name' => "'a'"]],
            ),
        );
    }

    public function testSqlForWritesAReplaceWhereTheStatementSaidToReplace(): void
    {
        self::assertStringStartsWith(
            'REPLACE INTO',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' REPLACE INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['id'],
                [],
                [['id' => '1']],
            ),
        );
    }

    public function testSqlForWritesAnInsertIgnoreWhereTheStatementSaidToIgnore(): void
    {
        self::assertStringStartsWith(
            'INSERT IGNORE INTO',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' IGNORE INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['id'],
                [],
                [['id' => '1']],
            ),
        );
    }

    public function testSqlForWritesTheColumnsEvenWhereTheFileHeldNoRows(): void
    {
        self::assertSame(
            'INSERT INTO `t` (`id`) SELECT NULL AS `id` WHERE FALSE',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['id'],
                [],
                [],
            ),
        );
    }

    public function testSqlForWritesTheColumnsInTheOrderTheTableDeclaresThem(): void
    {
        self::assertStringContainsString(
            '(`id`, `name`)',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['name', 'id'],
                [],
                [['id' => '1', 'name' => "'a'"]],
            ),
        );
    }

    public function testSqlForLeavesAColumnARowNeverWroteToTheTablesOwnDefault(): void
    {
        self::assertStringContainsString(
            'VALUES (1, DEFAULT)',
            (new MySqlLoadDataInsert())->sqlFor(
                MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t"),
                't',
                MySqlLoadStatements::definition(),
                ['id', 'name'],
                [],
                [['id' => '1']],
            ),
        );
    }

    public function testSqlForRefusesAStatementThatWouldWriteNoColumnAtAll(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataInsert())->sqlFor(
            MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t"),
            't',
            MySqlLoadStatements::definition(),
            ['@v'],
            [],
            [],
        );
    }
}
