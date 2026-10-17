<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\KeyNeeds;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyNeeds::class)]
#[Small]
final class KeyNeedsTest extends TestCase
{
    public function testDroppedRefusesAnIndexAForeignKeyNeeds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, CONSTRAINT k2 FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1553);
        $this->expectExceptionMessage("Cannot drop index 'k2': needed in a foreign key constraint");

        $session->query('ALTER TABLE c DROP INDEX k2');
    }

    public function testDroppedRefusesTheKeyAReferencingTableNeeds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1553);
        $this->expectExceptionMessage("Cannot drop index 'PRIMARY': needed in a foreign key constraint");

        $session->query('ALTER TABLE p DROP PRIMARY KEY');
    }

    public function testFollowedMakesReferencingKeysFollowARenamedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY, z INT); CREATE TABLE c (pid INT, CONSTRAINT x FOREIGN KEY (pid) REFERENCES p(id)); ALTER TABLE p RENAME COLUMN id TO idd');

        $result1 = $session->query('SHOW CREATE TABLE c')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertStringContainsString('CONSTRAINT `x` FOREIGN KEY (`pid`) REFERENCES `p` (`idd`)', (string) $result1->rows[0][1]);
    }

    public function testFollowedRefusesToDropAReferencedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY, z INT); CREATE TABLE c (pid INT, CONSTRAINT x FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1829);
        $this->expectExceptionMessage("Cannot drop column 'id': needed in a foreign key constraint 'x' of table 'c'");

        $session->query('ALTER TABLE p DROP COLUMN id');
    }

    public function testNamedFindsAKeyWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY Ka (a))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame('Ka', (new KeyNeeds($session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->named($table->definition, 'ka')?->name);
    }

    public function testCoveredTellsWhetherAKeptKeyStartsWithColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, KEY ka (a), KEY kab (a, b))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $needs = new KeyNeeds($session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([true, false], [$needs->covered($table->definition, [0], ['ka']), $needs->covered($table->definition, [0], ['ka', 'kab'])]);
    }

    public function testUniqueTellsWhetherADefinitionHasAUniqueKeyOfColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, UNIQUE (a))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $needs = new KeyNeeds($session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([true, false], [$needs->unique($table->definition, ['a']), $needs->unique($table->definition, ['b'])]);
    }
}
