<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Reading;
use MySqlMemory\System\Schema\Columns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Columns::class)]
#[Small]
final class ColumnsTest extends TestCase
{
    public function testRowsDescribesEachColumnOfEachTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query("CREATE TABLE t (a INT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'x', b VARCHAR(10) CHARSET latin1, c TEXT, d DATETIME(2), e ENUM('ab', 'c'), g POINT NOT NULL SRID 4326, f DECIMAL(5, 2) GENERATED ALWAYS AS (a / 2) STORED, PRIMARY KEY (a))");

        $result1 = $s->query("SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT, GENERATION_EXPRESSION, SRS_ID FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([
            ['a', '1', '3', 'NO', 'int', null, null, '10', '0', null, null, null, 'int unsigned', 'PRI', '', 'x', '', null],
            ['b', '2', null, 'YES', 'varchar', '10', '10', null, null, null, 'latin1', 'latin1_swedish_ci', 'varchar(10)', '', '', '', '', null],
            ['c', '3', null, 'YES', 'text', '65535', '65535', null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'text', '', '', '', '', null],
            ['d', '4', null, 'YES', 'datetime', null, null, null, null, '2', null, null, 'datetime(2)', '', '', '', '', null],
            ['e', '5', null, 'YES', 'enum', '2', '8', null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', "enum('ab','c')", '', '', '', '', null],
            ['g', '6', null, 'NO', 'point', null, null, null, null, null, null, null, 'point', '', '', '', '', '4326'],
            ['f', '7', null, 'YES', 'decimal', null, null, '5', '2', null, null, null, 'decimal(5,2)', '', 'STORED GENERATED', '', '(`a` / 2)', null],
        ], $result1->rows);
    }

    public function testRowsDescribesTheColumnsOfASystemTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');

        $result2 = $s->query("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, CHARACTER_SET_NAME, COLUMN_KEY, PRIVILEGES FROM information_schema.COLUMNS WHERE (TABLE_SCHEMA = 'mysql' AND TABLE_NAME = 'db' OR TABLE_NAME = 'SCHEMATA') AND ORDINAL_POSITION = 1 ORDER BY TABLE_SCHEMA DESC")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['Host', 'char', '255', '255', 'ascii', 'PRI', 'select,insert,update,references'], ['CATALOG_NAME', 'varchar', '64', '192', 'utf8mb3', '', 'select']], $result2->rows);
    }

    public function testColumnDescribesAColumnOfATable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a BIGINT UNSIGNED)');
        $table = $s->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['bigint', 20, 0, 'bigint unsigned'], [(new Columns())->column($table->definition, $table->definition->columns[0], 0, true, $reading)['DATA_TYPE'], (new Columns())->column($table->definition, $table->definition->columns[0], 0, true, $reading)['NUMERIC_PRECISION'], (new Columns())->column($table->definition, $table->definition->columns[0], 0, true, $reading)['NUMERIC_SCALE'], (new Columns())->column($table->definition, $table->definition->columns[0], 0, true, $reading)['COLUMN_TYPE']]);
    }

    public function testSystemDescribesAColumnOfASystemTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $column = $system->find('mysql', 'db')?->column('Select_priv');
        self::assertNotNull($column);

        self::assertSame(['enum', 1, 3, 'N', 'select,insert,update,references'], [(new Columns())->system($column, 3, 'mysql', $reading)['DATA_TYPE'], (new Columns())->system($column, 3, 'mysql', $reading)['CHARACTER_MAXIMUM_LENGTH'], (new Columns())->system($column, 3, 'mysql', $reading)['CHARACTER_OCTET_LENGTH'], (new Columns())->system($column, 3, 'mysql', $reading)['COLUMN_DEFAULT'], (new Columns())->system($column, 3, 'mysql', $reading)['PRIVILEGES']]);
    }

    public function testNameAnswersTheNameOfAType(): void
    {
        self::assertSame(['decimal', 'enum', 'bigint'], [(new Columns())->name('decimal(5,2) unsigned'), (new Columns())->name("enum('a','b')"), (new Columns())->name('BIGINT')]);
    }

    public function testNumericsAnswersThePrecisionAndScaleOfASystemType(): void
    {
        self::assertSame([[20, 0], [19, 0], [10, 2], [22, null], [12, null], [null, null]], [(new Columns())->numerics('bigint unsigned', 'bigint', 0), (new Columns())->numerics('bigint', 'bigint', 0), (new Columns())->numerics('decimal(10,2)', 'decimal', 10), (new Columns())->numerics('double', 'double', 0), (new Columns())->numerics('float', 'float', 0), (new Columns())->numerics('varchar(64)', 'varchar', 64)]);
    }

    public function testLengthsCountCharactersAndBytes(): void
    {
        self::assertSame([[3, 12], [4, 16], [65535, 65535], [null, null]], [(new Columns())->lengths(Field::String, 3, [], 4, true), (new Columns())->lengths(Field::Set, 0, ['a', 'bc'], 4, true), (new Columns())->lengths(Field::Blob, 65535, [], 4, true), (new Columns())->lengths(Field::Long, 11, [], 1, false)]);
    }

    public function testPrecisionAnswersDigitsAndScale(): void
    {
        self::assertSame([[7, 0], [8, 0], [12, null], [10, 3], [5, null], [null, null]], [(new Columns())->precision(Field::Int24, 9, 0, false, 0, 'mediumint'), (new Columns())->precision(Field::Int24, 8, 0, true, 0, 'mediumint unsigned'), (new Columns())->precision(Field::Float, 12, 31, false, 0, 'float'), (new Columns())->precision(Field::Double, 10, 3, false, 0, 'double(10,3)'), (new Columns())->precision(Field::Bit, 5, 0, false, 0, 'bit(5)'), (new Columns())->precision(Field::Date, 10, 0, false, 0, 'date')]);
    }

    public function testSridAnswersTheSystemASpatialColumnIsRestrictedTo(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (g POINT NOT NULL SRID 4326, h POINT)');
        $table = $s->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([4326, null], [(new Columns())->srid($table->definition, $table->definition->columns[0]), (new Columns())->srid($table->definition, $table->definition->columns[1])]);
    }
}
