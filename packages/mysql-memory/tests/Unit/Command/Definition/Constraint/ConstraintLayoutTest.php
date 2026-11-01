<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\ConstraintLayout;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;

#[CoversClass(ConstraintLayout::class)]
#[Small]
final class ConstraintLayoutTest extends TestCase
{
    public function testElementsWritesEachConstraintWithItsName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (a INT, CHECK (a > 0), FOREIGN KEY (a) REFERENCES p(id))');
        $table = $session->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        $elements = (new ConstraintLayout())->elements($table->definition);

        self::assertSame([ForeignKey::class, CheckConstraint::class], array_map(static fn ($element): string => $element::class, $elements));
    }

    public function testForeignWritesTheReferencedTableWithItsDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES p(id))');
        $table = $session->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        $element = (new ConstraintLayout())->foreign($table->definition->foreignKeys[0], $table->definition);

        self::assertSame(['d', 'p', 'c_ibfk_1'], [$element->references->table->schema?->value, $element->references->table->name->value, $element->constraint?->name?->column->value]);
    }

    public function testNamedAnswersAConstraintNameClause(): void
    {
        self::assertSame('x', (new ConstraintLayout())->named('x')->name?->column->value);
    }

    public function testUncheckedLeavesOutTheCheckConstraintsOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT CHECK (a > 0) NOT ENFORCED); ALTER TABLE t ADD b INT');

        $shown1 = $session->query('SHOW CREATE TABLE t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown1);

        self::assertSame(
            "CREATE TABLE `t` (\n  `a` int DEFAULT NULL,\n  `b` int DEFAULT NULL,\n  CONSTRAINT `t_chk_1` CHECK ((`a` > 0)) /*!80016 NOT ENFORCED */\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            $shown1->rows[0][1],
        );
    }

    public function testRenamedMakesTheNamesTheServerGaveFollowTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, CHECK (a > 0), CONSTRAINT x CHECK (a < 9)); RENAME TABLE t TO u');
        $shown2 = $session->query('SHOW CREATE TABLE u')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown2);

        self::assertSame(
            "CREATE TABLE `u` (\n  `a` int DEFAULT NULL,\n  CONSTRAINT `u_chk_1` CHECK ((`a` > 0)),\n  CONSTRAINT `x` CHECK ((`a` < 9))\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            $shown2->rows[0][1],
        );
    }

    public function testAdaptedDropsACheckConstraintWithTheOnlyColumnItReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE k2 (a INT CHECK (a > 0), b INT); ALTER TABLE k2 DROP COLUMN a');

        $result1 = $session->query('SHOW CREATE TABLE k2')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame("CREATE TABLE `k2` (\n  `b` int DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci", $result1->rows[0][1]);
    }

    public function testAdaptedRefusesToRenameAColumnACheckConstraintReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE k (a INT, b INT, CONSTRAINT cc CHECK (a > b))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3959);
        $this->expectExceptionMessage("Check constraint 'cc' uses column 'a', hence column cannot be dropped or renamed.");

        $session->query('ALTER TABLE k RENAME COLUMN a TO a2');
    }

    public function testDependenciesRefusesToDropAColumnAGeneratedColumnReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE g (a INT, b INT AS (a))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3108);
        $this->expectExceptionMessage("Column 'a' has a generated column dependency.");

        $session->query('ALTER TABLE g DROP COLUMN a');
    }

    public function testHighestNumbersANewConstraintAfterTheHighest(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE u (a INT); ALTER TABLE u ADD CONSTRAINT u_chk_9 CHECK (a > 0); ALTER TABLE u ADD CHECK (a > 1)');

        $result2 = $session->query('SHOW CREATE TABLE u')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertStringContainsString('CONSTRAINT `u_chk_10` CHECK ((`a` > 1))', (string) $result2->rows[0][1]);
    }
}
