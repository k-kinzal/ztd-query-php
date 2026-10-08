<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\ShowKeysCommand;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowKeysCommand::class)]
#[Small]
final class ShowKeysCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowKeysCommand())->clearsDiagnostics());
    }

    public function testExecuteListsEachColumnOfEachKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10), c DECIMAL(5,2) NOT NULL, KEY kb (b(3), c DESC))');

        $result = $session->query('SHOW INDEX FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([
            ['t', '0', 'PRIMARY', '1', 'a', 'A', '0', null, null, '', 'BTREE', '', '', 'YES', null],
            ['t', '1', 'kb', '1', 'b', 'A', '0', '3', null, 'YES', 'BTREE', '', '', 'YES', null],
            ['t', '1', 'kb', '2', 'c', 'D', '0', null, null, '', 'BTREE', '', '', 'YES', null],
        ], $result->rows);
    }

    public function testExecuteFiltersByWhere(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b INT, KEY kb (b))');

        $result = $session->query("SHOW KEYS FROM t WHERE Key_name = 'kb'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['kb'], array_column($result->rows, 2));
    }

    public function testExecuteKeepsTheCardinalityItReadFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE s (a INT PRIMARY KEY, b INT, KEY (b)); INSERT INTO s VALUES (1,1),(2,1),(3,2)');

        $first = $session->query('SHOW INDEX FROM s')[0];
        $session->query('INSERT INTO s VALUES (4,1),(5,1),(6,2)');
        $second = $session->query('SHOW INDEX FROM s')[0];

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame(['3', '2'], array_column($first->rows, 6));
        self::assertSame(['3', '2'], array_column($second->rows, 6));
    }

    public function testCardinalityCountsDistinctPrefixesOrUniqueRows(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE i (a INT PRIMARY KEY, b VARCHAR(5), c INT, KEY k (b(2), c)); INSERT INTO i VALUES (1,'aaa',1),(2,'AAb',1),(3,'b',NULL),(4,'b',NULL)");
        $session->query('CREATE TABLE m (a INT NOT NULL, b INT, UNIQUE KEY u (a), KEY k (b)) ENGINE=MyISAM; INSERT INTO m VALUES (1,1),(2,1)');
        $innodb = $session->instance->dictionary->table('d', 'i');
        $myisam = $session->instance->dictionary->table('d', 'm');

        self::assertNotNull($innodb);
        self::assertNotNull($myisam);
        self::assertSame([2, 2], [(new ShowKeysCommand())->cardinality($innodb, $innodb->definition->keys[1], 0), (new ShowKeysCommand())->cardinality($innodb, $innodb->definition->keys[1], 1)]);
        self::assertSame([2, null], [(new ShowKeysCommand())->cardinality($myisam, $myisam->definition->keys[0], 0), (new ShowKeysCommand())->cardinality($myisam, $myisam->definition->keys[1], 0)]);
    }

    public function testTypeNamesTheIndexType(): void
    {
        self::assertSame(['BTREE', 'FULLTEXT', 'SPATIAL', 'HASH'], [
            (new ShowKeysCommand())->type(KeyKind::Index, 'InnoDB'),
            (new ShowKeysCommand())->type(KeyKind::FullText, 'InnoDB'),
            (new ShowKeysCommand())->type(KeyKind::Spatial, 'InnoDB'),
            (new ShowKeysCommand())->type(KeyKind::Unique, 'MEMORY'),
        ]);
    }

    public function testHeadingsNameTheColumnsOfShowStatistics(): void
    {
        $headings = (new ShowKeysCommand())->headings();

        self::assertCount(15, $headings);
        self::assertSame(['Table', 'Non_unique', 'Key_name'], array_map(static fn (Heading $heading): string => $heading->name, array_slice($headings, 0, 3)));
        self::assertSame('SHOW_STATISTICS', $headings[0]->table);
    }

    public function testTemporaryReadsTheIndexesOfATemporaryTableApart(): void
    {
        $headings = (new ShowKeysCommand())->temporary();

        self::assertSame(['TMP_TABLE_KEYS', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong], [$headings[0]->table, $headings[1]->field]);
    }
}
