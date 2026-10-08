<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\Schemata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schemata::class)]
#[Small]
final class SchemataTest extends TestCase
{
    public function testRowsListsTheDatabasesByName(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');

        $result1 = $s->query('SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME, SQL_PATH, DEFAULT_ENCRYPTION FROM information_schema.SCHEMATA')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['d', 'utf8mb4', 'utf8mb4_0900_ai_ci', null, 'NO'], ['information_schema', 'utf8mb3', 'utf8mb3_general_ci', null, 'NO'], ['mysql', 'utf8mb4', 'utf8mb4_0900_ai_ci', null, 'NO'], ['performance_schema', 'utf8mb4', 'utf8mb4_0900_ai_ci', null, 'NO'], ['sys', 'utf8mb4', 'utf8mb4_0900_ai_ci', null, 'NO']], $result1->rows);
    }

    public function testRowsListsInformationSchemaFirstInMySql57(): void
    {
        $s = (new Instance('5.7.44'))->connect();
        $s->query('CREATE DATABASE a');

        $result2 = $s->query('SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['information_schema', 'utf8', 'utf8_general_ci'], ['a', 'latin1', 'latin1_swedish_ci'], ['mysql', 'latin1', 'latin1_swedish_ci'], ['performance_schema', 'utf8', 'utf8_general_ci'], ['sys', 'utf8', 'utf8_general_ci']], $result2->rows);
    }

    public function testRowsListsTheDatabasesInCreationOrderInMySql80(): void
    {
        $s = (new Instance('8.0.44'))->connect();
        $s->query('CREATE DATABASE b');
        $s->query('CREATE DATABASE a');

        $result3 = $s->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame([['mysql'], ['information_schema'], ['performance_schema'], ['sys'], ['b'], ['a']], $result3->rows);
    }
}
