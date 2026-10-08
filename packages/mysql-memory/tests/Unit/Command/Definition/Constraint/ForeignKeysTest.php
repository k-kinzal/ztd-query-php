<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\ForeignKeys;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForeignKeys::class)]
#[Small]
final class ForeignKeysTest extends TestCase
{
    public function testWrittenNamesTheUnnamedKeysAndImplicitTheirIndexes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY, b INT, UNIQUE (id, b)); CREATE TABLE c (a INT, b INT, CONSTRAINT myfk FOREIGN KEY (a) REFERENCES p(id), FOREIGN KEY (a, b) REFERENCES p(id, b), CONSTRAINT FOREIGN KEY ix (b) REFERENCES p(id) ON DELETE CASCADE ON UPDATE SET NULL, KEY kb (b))');

        $shown1 = $session->query('SHOW CREATE TABLE c')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown1);

        self::assertSame(
            "CREATE TABLE `c` (\n  `a` int DEFAULT NULL,\n  `b` int DEFAULT NULL,\n  KEY `a` (`a`,`b`),\n  KEY `kb` (`b`),\n  CONSTRAINT `c_ibfk_1` FOREIGN KEY (`a`, `b`) REFERENCES `p` (`id`, `b`),\n  CONSTRAINT `c_ibfk_2` FOREIGN KEY (`b`) REFERENCES `p` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,\n  CONSTRAINT `myfk` FOREIGN KEY (`a`) REFERENCES `p` (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            $shown1->rows[0][1],
        );
    }

    public function testImplicitRefusesAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1072);
        $this->expectExceptionMessage("Key column 'x' doesn't exist in table");

        $session->query('CREATE TABLE c (a INT, FOREIGN KEY (x) REFERENCES p(id))');
    }

    public function testPrunedDropsAnIndexAnotherOneStartsWith(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (a INT, b INT, FOREIGN KEY (a) REFERENCES p(id), KEY (a, b))');
        $table = $session->instance->dictionary->table('d', 'c');

        self::assertNotNull($table);
        self::assertSame([['a', [0, 1]]], array_map(static fn (Key $key): array => [$key->name, $key->columns], $table->definition->keys));
    }

    public function testStartsTellsWhetherAKeyStartsWithWholeColumns(): void
    {
        self::assertSame([true, false, false], [ForeignKeys::starts(new Key('k', KeyKind::Index, [1, 2]), [1]), ForeignKeys::starts(new Key('k', KeyKind::Index, [1, 2], [3]), [1]), ForeignKeys::starts(new Key('k', KeyKind::Index, [2, 1]), [1])]);
    }

    public function testBuiltRefusesANameUsedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1826);
        $this->expectExceptionMessage("Duplicate foreign key constraint name 'fk'");

        $session->query('CREATE TABLE c (a INT, CONSTRAINT fk FOREIGN KEY (a) REFERENCES p(id), CONSTRAINT fk FOREIGN KEY (a) REFERENCES p(id))');
    }

    public function testCheckedRefusesAMissingReferencedTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1824);
        $this->expectExceptionMessage("Failed to open the referenced table 'nope'");

        $session->query('CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES nope(id))');
    }

    public function testCheckedRefusesAColumnCountThatDoesNotMatch(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1239);
        $this->expectExceptionMessage("Incorrect foreign key definition for 'foreign key without name': Key reference and table reference don't match");

        $session->query('CREATE TABLE c (a INT, b INT, FOREIGN KEY (a, b) REFERENCES p(id))');
    }

    public function testUncheckedKeepsAKeyToAMissingTableWithoutForeignKeyChecks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; SET foreign_key_checks = 0; CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES nope(id))');

        $result1 = $session->query('SHOW CREATE TABLE c')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertStringContainsString('CONSTRAINT `c_ibfk_1` FOREIGN KEY (`a`) REFERENCES `nope` (`id`)', (string) $result1->rows[0][1]);
    }

    public function testActionRefusesSetNullOnANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1830);
        $this->expectExceptionMessage("Column 'a' cannot be NOT NULL: needed in a foreign key constraint 'c_ibfk_1' SET NULL");

        $session->query('CREATE TABLE c (a INT NOT NULL, FOREIGN KEY (a) REFERENCES p(id) ON DELETE SET NULL)');
    }

    public function testStoredTellsWhetherAStoredGeneratedColumnReadsAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1215);
        $this->expectExceptionMessage('Cannot add foreign key constraint');

        $session->query('CREATE TABLE c (id INT PRIMARY KEY, pid INT, g INT AS (pid * 2) STORED, FOREIGN KEY (pid) REFERENCES p(id) ON DELETE CASCADE)');
    }

    public function testIndexedRefusesAReferencedKeyThatIsNotUniqueFrom84On(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT, KEY (id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6125);
        $this->expectExceptionMessage("Failed to add the foreign key constraint. Missing unique key for constraint 'c_ibfk_1' in the referenced table 'p'");

        $session->query('CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES p(id))');
    }

    public function testIndexedTakesAnIndexThatStartsWithTheColumnsIn80(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT, KEY (id)); CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES p(id))');

        self::assertNotNull($session->instance->dictionary->table('d', 'c'));
    }

    public function testCompatibleTellsWhetherTwoColumnsCanBeLinked(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id VARCHAR(10) PRIMARY KEY); CREATE TABLE c (a VARCHAR(20), FOREIGN KEY (a) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3780);
        $this->expectExceptionMessage("Referencing column 'a' and referenced column 'id' in foreign key constraint 'c2_ibfk_1' are incompatible.");

        $session->query('CREATE TABLE c2 (a VARCHAR(20) CHARACTER SET latin1, FOREIGN KEY (a) REFERENCES p(id))');
    }
}
