<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\Definitions;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Definitions::class)]
#[Small]
final class DefinitionsTest extends TestCase
{
    public function testTableTakesTheNameEngineAndCollationOfItsOptions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) ENGINE=MyISAM CHARSET=latin1');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertNotNull($definition);
        self::assertSame(['d', 't', 'MyISAM', 'latin1_swedish_ci', false], [$definition->schema, $definition->name, $definition->engine, $definition->collation, $definition->temporary]);
    }

    public function testTableIsTemporaryForCreateTemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t (a INT)');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertNotNull($definition);
        self::assertSame(['InnoDB', true], [$definition->engine, $definition->temporary]);
    }

    public function testCollationFallsBackToTheCollationOfTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d COLLATE latin1_bin; USE d; CREATE TABLE t (a VARCHAR(4))');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertNotNull($definition);
        self::assertSame(['latin1_bin', 'latin1_bin'], [$definition->collation, $definition->columns[0]->domain->collation->name]);
    }

    public function testCollationTakesTheCollateOptionOverTheCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a VARCHAR(4)) CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertNotNull($definition);
        self::assertSame(['utf8mb4_bin', 'utf8mb4_bin'], [$definition->collation, $definition->columns[0]->domain->collation->name]);
    }

    public function testColumnTakesItsTypeCollationNullabilityAndComment(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT NOT NULL COMMENT 'hi', b VARCHAR(4) COLLATE utf8mb4_bin)");
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame(
            [['a', Field::Long, 11, false, 'hi'], ['b', Field::VarString, 4, true, '']],
            [[$columns[0]->name, $columns[0]->domain->field, $columns[0]->domain->length, $columns[0]->nullable(), $columns[0]->comment], [$columns[1]->name, $columns[1]->domain->field, $columns[1]->domain->length, $columns[1]->nullable(), $columns[1]->comment]],
        );
        self::assertSame('utf8mb4_bin', $columns[1]->domain->collation->name);
    }

    public function testColumnMakesSerialAnUnsignedNotNullAutoIncrementBigint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a SERIAL)');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertNotNull($definition);
        self::assertSame([Field::LongLong, true, false, true], [$definition->columns[0]->domain->field, $definition->columns[0]->domain->unsigned, $definition->columns[0]->nullable(), $definition->columns[0]->autoIncrement]);
        self::assertSame([['a', KeyKind::Unique, [0]]], [[$definition->keys[0]->name, $definition->keys[0]->kind, $definition->keys[0]->columns]]);
    }

    public function testDefaultFillsTheColumnsARowDoesNotName(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 5, b INT, c VARCHAR(3) DEFAULT 'x', d DECIMAL(5,2) DEFAULT 1.5, e INT DEFAULT (1 + 1))");

        $session->query('INSERT INTO t () VALUES ()');
        $result = $session->query('SELECT a, b, c, d, e FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', null, 'x', '1.50', '2']], $result->rows);
    }

    public function testDefaultKeepsTheTextOfAConstantDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 5, b INT, c INT NOT NULL)');
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame(
            [[true, 5, '5'], [true, null, null], [false, null, null]],
            [[$columns[0]->default->declared, $columns[0]->default->value, $columns[0]->default->text], [$columns[1]->default->declared, $columns[1]->default->value, $columns[1]->default->text], [$columns[2]->default->declared, $columns[2]->default->value, $columns[2]->default->text]],
        );
    }

    public function testDefaultGivesNoDefaultToANotNullColumnWithoutOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, c INT NOT NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);
        $this->expectExceptionMessage("Field 'c' doesn't have a default value");

        $session->query('INSERT INTO t (a) VALUES (1)');
    }

    public function testDefaultAcceptsAnExpressionDefaultForJson(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b JSON DEFAULT ('[]'))");

        $session->query('INSERT INTO t (a) VALUES (1)');
        $result = $session->query('SELECT b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[]']], $result->rows);
    }

    public function testDefaultRefusesALiteralDefaultForABlob(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1101);
        $this->expectExceptionMessage("BLOB, TEXT, GEOMETRY or JSON column 'a' can't have a default value");

        $session->query("CREATE TABLE t (a BLOB DEFAULT 'x')");
    }

    public function testDefaultRefusesNullForANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1067);
        $this->expectExceptionMessage("Invalid default value for 'a'");

        $session->query('CREATE TABLE t (a INT NOT NULL DEFAULT NULL)');
    }

    public function testDefaultRefusesAValueTheColumnCannotHold(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1067);
        $this->expectExceptionMessage("Invalid default value for 'a'");

        $session->query('CREATE TABLE t (a TINYINT DEFAULT 300)');
    }

    public function testOnUpdateTellsWhetherAColumnTakesTheTimeOfAnUpdate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP, b TIMESTAMP NULL)');
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame([true, false], [$columns[0]->onUpdateNow, $columns[1]->onUpdateNow]);
    }

    public function testInlineKeysDeclaresThePrimaryAndUniqueKeysOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT UNIQUE, b INT PRIMARY KEY)');
        $keys = $session->instance->dictionary->table('d', 't')?->definition->keys ?? [];

        self::assertSame(
            [['PRIMARY', KeyKind::Primary, [1]], ['a', KeyKind::Unique, [0]]],
            [[$keys[0]->name, $keys[0]->kind, $keys[0]->columns], [$keys[1]->name, $keys[1]->kind, $keys[1]->columns]],
        );
    }

    public function testKeyTakesTheNameKindColumnsAndPrefixesOfAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(10), c VARCHAR(10), INDEX idx (a, b(3)), CONSTRAINT u1 UNIQUE (c), FULLTEXT KEY f (b))');
        $keys = $session->instance->dictionary->table('d', 't')?->definition->keys ?? [];

        self::assertSame(
            [['idx', KeyKind::Index, [0, 1], [null, 3]], ['u1', KeyKind::Unique, [2], [null]], ['f', KeyKind::FullText, [1], [null]]],
            [[$keys[0]->name, $keys[0]->kind, $keys[0]->columns, $keys[0]->prefixes], [$keys[1]->name, $keys[1]->kind, $keys[1]->columns, $keys[1]->prefixes], [$keys[2]->name, $keys[2]->kind, $keys[2]->columns, $keys[2]->prefixes]],
        );
    }

    public function testNamedNamesAnUnnamedKeyAfterItsFirstColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, UNIQUE (a), KEY (a, b), KEY (b))');
        $keys = $session->instance->dictionary->table('d', 't')?->definition->keys ?? [];

        self::assertSame(['a', 'a_2', 'b'], [$keys[0]->name, $keys[1]->name, $keys[2]->name]);
    }

    public function testNamedRefusesTwoKeysOfTheSameName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1061);
        $this->expectExceptionMessage("Duplicate key name 'k'");

        $session->query('CREATE TABLE t (a INT, KEY k (a), KEY k (a))');
    }

    public function testCheckAcceptsAnAutoIncrementColumnThatLeadsAKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (a, b))');

        self::assertSame(0, $session->instance->dictionary->table('d', 't')?->definition->autoIncrementColumn());
    }

    public function testCheckRefusesAnAutoIncrementColumnOutsideAnyKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1075);
        $this->expectExceptionMessage('Incorrect table definition; there can be only one auto column and it must be defined as a key');

        $session->query('CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b, a))');
    }

    public function testCheckRefusesTwoAutoIncrementColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1075);
        $this->expectExceptionMessage('Incorrect table definition; there can be only one auto column and it must be defined as a key');

        $session->query('CREATE TABLE t (a INT AUTO_INCREMENT, b INT AUTO_INCREMENT, KEY (a), KEY (b))');
    }

    public function testCheckRefusesTwoPrimaryKeys(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1068);
        $this->expectExceptionMessage('Multiple primary key defined');

        $session->query('CREATE TABLE t (a INT PRIMARY KEY, b INT, PRIMARY KEY (b))');
    }

    public function testDeclaredFindsTheDeclarationOfAnInvisibleColumnByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE v (a INT, e INT INVISIBLE, f DECIMAL(5,2))');
        $definition = $session->instance->dictionary->table('d', 'v')?->definition;

        self::assertNotNull($definition);
        self::assertSame([true, Field::Long, Field::NewDecimal, 2], [$definition->columns[1]->invisible, $definition->columns[1]->domain->field, $definition->columns[2]->domain->field, $definition->columns[2]->domain->decimals]);
        self::assertSame([$definition->declaration->implicit[0]->column, $definition->declaration->columns[1]], [$definition->columns[1]->declaration, $definition->columns[2]->declaration]);
    }

    public function testDefaultKeepsTheClockOfCurrentTimestampForEachStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (c TIMESTAMP DEFAULT CURRENT_TIMESTAMP, d DATETIME(3) DEFAULT NOW(3), e DATETIME DEFAULT LOCALTIMESTAMP)');
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame([[true, null, 'CURRENT_TIMESTAMP'], [true, null, 'CURRENT_TIMESTAMP(3)'], [true, null, 'CURRENT_TIMESTAMP']], array_map(static fn ($column): array => [$column->default->now, $column->default->value, $column->default->text], $columns));
        self::assertNotNull($columns[1]->default->expression);
    }

    public function testCheckRefusesATableWithoutAVisibleColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4028);
        $this->expectExceptionMessage('A table must have at least one visible column.');

        $session->query('CREATE TABLE t (a INT INVISIBLE)');
    }

    public function testMembersHoldsTheMembersInTheCharacterSetOfTheColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (e ENUM('é','b') CHARACTER SET latin1, s SET('é','x') CHARACTER SET latin1)");
        $session->query("INSERT INTO t VALUES ('é', 'é,x')");
        $table = $session->instance->dictionary->table('d', 't');
        $result = $session->query("SELECT e, s, HEX(e), HEX(s), e = 'é' FROM t")[0];

        self::assertNotNull($table);
        self::assertSame([["\xE9", 'b'], 1, 3], [$table->definition->columns[0]->domain->members, $table->definition->columns[0]->domain->length, $table->definition->columns[1]->domain->length]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['é', 'é,x', 'E9', 'E92C78', '1']], $result->rows);
    }

    public function testEngineTakesTheLastEngineOptionOrInnoDB(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) ENGINE=InnoDB ENGINE=MyISAM; CREATE TABLE u (a INT)');

        self::assertSame(['MyISAM', 'InnoDB'], [$session->instance->dictionary->table('d', 't')?->definition->engine, $session->instance->dictionary->table('d', 'u')?->definition->engine]);
    }
}
