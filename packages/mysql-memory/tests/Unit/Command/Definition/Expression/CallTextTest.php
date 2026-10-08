<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\CallText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallText::class)]
#[Small]
final class CallTextTest extends TestCase
{
    public function testFunctionWritesTheNameTheServerKeeps(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(9), w VARCHAR(9) AS (ucase(b)), x VARCHAR(9) AS (mid(b,1,2)), y DOUBLE AS (power(a,2)), z INT AS (isnull(a)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['upper(`b`)', 'substr(`b`,1,2)', 'pow(`a`,2)', '(`a` is null)'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 2)));
    }

    public function testKeywordWritesDateFunctionsAsCasts(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (d DATETIME, x DATE AS (date(d)), y TIME AS (time(d)), z INT AS (year(d)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['cast(`d` as date)', 'cast(`d` as time)', 'year(`d`)'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 1)));
    }

    public function testTargetNamesTheCharacterSetOfACastToChar(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(5), x VARCHAR(9) AS (cast(a as char(5))), y VARCHAR(9) AS (cast(b as binary)), z DECIMAL(5,2) AS (cast(a as decimal(5,2))))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['cast(`a` as char(5) charset utf8mb4)', 'cast(`b` as char charset binary)', 'cast(`a` as decimal(5,2))'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 2)));
    }

    public function testIntervalWritesDateArithmeticAsTheAdditionOfAnInterval(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (d DATE, x DATE AS (date_sub(d, INTERVAL 2 MONTH)), y DATE AS (INTERVAL 1 DAY + d), z DATE AS (adddate(d, 3)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['(`d` - interval 2 month)', '(`d` + interval 1 day)', '(`d` + interval 3 day)'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 1)));
    }

    public function testJsonWritesThePathOperatorsAsJsonExtract(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (j JSON, x JSON AS (j->'$.a'), y VARCHAR(9) AS (j->>'$.a'))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(["json_extract(`j`,_utf8mb4'$.a')", "json_unquote(json_extract(`j`,_utf8mb4'$.a'))"], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression]);
    }

    public function testTrimWritesTheSideAndTheRemovedString(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (b VARCHAR(9), x VARCHAR(9) AS (trim(leading 'a' from b)), y VARCHAR(9) AS (trim(b)))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(["trim(leading _utf8mb4'a' from `b`)", 'trim(`b`)'], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression]);
    }

    public function testScalarWritesExtractAndPosition(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (d DATE, b VARCHAR(5), x INT AS (extract(year from d)), y INT AS (position('a' in b)))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['extract(year from `d`)', "locate(_utf8mb4'a',`b`)"], [$table->definition->columns[2]->expression, $table->definition->columns[3]->expression]);
    }

    public function testCallWritesArgumentsWithoutSpaces(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (greatest(a, 1, 2)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('greatest(`a`,1,2)', $table->definition->columns[1]->expression);
    }

    public function testArithmeticWritesEachFormOfTemporalArithmeticAsAnInterval(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (d DATE, x DATE AS (d - INTERVAL 2 MONTH), y DATE AS (INTERVAL 1 DAY + d), z DATE AS (date_add(d, INTERVAL 3 WEEK)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['(`d` - interval 2 month)', '(`d` + interval 1 day)', '(`d` + interval 3 week)'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 1)));
    }
}
