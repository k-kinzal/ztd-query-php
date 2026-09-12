<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\InsertQuery;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[CoversClass(InsertQuery::class)]
final class InsertQueryTest extends TestCase
{
    public function testOrderedColumns(): void
    {
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name', 'score'], [], [], [], [], generatedExpressions: ['score' => 'id * 2']);
        $query = new InsertQuery(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter());
        self::assertSame(['id', 'name'], $query->orderedColumns(['name', '@raw', 'id'], ['name' => '@raw'], $definition));
    }

    public function testBuildInsertSql(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name', 'score'], [], [], [], [], generatedExpressions: ['score' => 'id * 2']);
        $query = new InsertQuery(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter());
        self::assertSame("INSERT INTO `t` (`id`, `name`) VALUES (1, 'hello'), (2, DEFAULT)", $query->buildInsertSql($statement, 't', $definition, ['id', 'name'], [], [['id' => '1', 'name' => "'hello'"], ['id' => '2']]));
        self::assertSame('INSERT INTO `t` (`id`) SELECT NULL AS `id` WHERE FALSE', $query->buildInsertSql($statement, 't', $definition, ['id'], [], []));
    }

    public function testBuildInsertSqlLocalAndReplace(): void
    {
        $sql = "LOAD DATA LOCAL INFILE 'input.csv' INTO TABLE t";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name', 'score'], [], [], [], [], generatedExpressions: ['score' => 'id * 2']);
        $query = new InsertQuery(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter());
        self::assertSame('INSERT IGNORE INTO `t` (`id`) VALUES (1)', $query->buildInsertSql($statement, 't', $definition, ['id'], [], [['id' => '1']]));
        $statement->replace_ignore = 'REPLACE';
        self::assertSame('REPLACE INTO `t` (`id`) VALUES (1)', $query->buildInsertSql($statement, 't', $definition, ['id'], [], [['id' => '1']]));
    }

}
