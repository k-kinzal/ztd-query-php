<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ShowCreateTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowCreateTableCommand::class)]
#[Small]
final class ShowCreateTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCreateTableCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheStatementOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10) DEFAULT 'x', c DECIMAL(5,2) UNSIGNED NOT NULL, KEY kb (b(3), c DESC))");

        $result = $session->query('SHOW CREATE TABLE t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['t', "CREATE TABLE `t` (\n  `a` int NOT NULL,\n  `b` varchar(10) DEFAULT 'x',\n  `c` decimal(5,2) unsigned NOT NULL,\n  PRIMARY KEY (`a`),\n  KEY `kb` (`b`(3),`c` DESC)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"]], $result->rows);
        self::assertSame([256, 4096], [$result->columns[0]->length, $result->columns[1]->length]);
    }

    public function testExecuteWritesTheStatementOfAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t');

        $result = $session->query('SHOW CREATE TABLE v')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['v', 'CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v` AS select `t`.`a` AS `a` from `t`', 'utf8mb4', 'utf8mb4_0900_ai_ci']], $result->rows);
        self::assertSame('Create View', $result->columns[1]->name);
    }

    public function testExecuteRefusesAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.name' doesn't exist");

        $session->query('SHOW CREATE TABLE `name`');
    }

    public function testStatementWritesTheNextAutoIncrementValueAndTheComment(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE `we``ird` (`c``x` INT AUTO_INCREMENT PRIMARY KEY) ENGINE=myisam DEFAULT CHARSET=latin1 COMMENT 'x''y'; INSERT INTO `we``ird` VALUES (NULL), (NULL)");
        $table = $session->instance->dictionary->table('d', 'we`ird');

        self::assertNotNull($table);
        self::assertSame("CREATE TABLE `we``ird` (\n  `c``x` int NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`c``x`)\n) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='x''y'", (new ShowCreateTableCommand())->statement($table));
    }

    public function testColumnWritesNullForATimestampAndInvisibleInAComment(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a TIMESTAMP NULL, b VARCHAR(20) INVISIBLE COMMENT 'hi')");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('`a` timestamp NULL DEFAULT NULL', (new ShowCreateTableCommand())->column($table->definition->columns[0], $table->definition));
        self::assertSame("`b` varchar(20) DEFAULT NULL /*!80023 INVISIBLE */ COMMENT 'hi'", (new ShowCreateTableCommand())->column($table->definition->columns[1], $table->definition));
    }

    public function testCollationWritesANamedOrNonDefaultCollation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (n VARCHAR(5) COLLATE latin1_bin, m VARCHAR(3) CHARACTER SET latin1, o VARCHAR(2), p INT) DEFAULT CHARSET=latin1 COLLATE=latin1_bin');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(
            [' CHARACTER SET latin1 COLLATE latin1_bin', ' CHARACTER SET latin1 COLLATE latin1_swedish_ci', ' COLLATE latin1_bin', ''],
            array_map(static fn ($column): string => (new ShowCreateTableCommand())->collation($column, $table->definition), $table->definition->columns),
        );
    }

    public function testKeyWritesEachKindOfKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b TEXT, c INT, PRIMARY KEY (a), UNIQUE KEY u (c), FULLTEXT KEY f (b), KEY k (b(10)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['PRIMARY KEY (`a`)', 'UNIQUE KEY `u` (`c`)', 'FULLTEXT KEY `f` (`b`)', 'KEY `k` (`b`(10))'], array_map(static fn ($key): string => (new ShowCreateTableCommand())->key($key, $table->definition), $table->definition->keys));
    }

    public function testOptionsWritesTheCollationOfUtf8mb4Always(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE l (a INT) CHARSET latin1; CREATE TABLE b (a INT) COLLATE latin1_bin');
        $t = $session->instance->dictionary->table('d', 't');
        $l = $session->instance->dictionary->table('d', 'l');
        $b = $session->instance->dictionary->table('d', 'b');

        self::assertNotNull($t);
        self::assertNotNull($l);
        self::assertNotNull($b);
        self::assertSame('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci', (new ShowCreateTableCommand())->options($t));
        self::assertSame('ENGINE=InnoDB DEFAULT CHARSET=latin1', (new ShowCreateTableCommand())->options($l));
        self::assertSame('ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin', (new ShowCreateTableCommand())->options($b));
    }

    public function testCommentReadsTheCommentOption(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT) COMMENT='tbl c'");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('tbl c', (new ShowCreateTableCommand())->comment($table->definition));
    }

    public function testNameQuotesAnIdentifier(): void
    {
        self::assertSame('`a``b`', (new ShowCreateTableCommand())->name('a`b'));
    }
}
