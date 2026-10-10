<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ColumnDefinition::class)]
#[Small]
final class ColumnDefinitionTest extends TestCase
{
    public function testNullableFollowsTheNullabilityOfTheDomain(): void
    {
        $nullable = new ColumnDefinition('a', Domain::integer(Field::Long, 11)->withNullable(true), Fill::none());
        $required = new ColumnDefinition('b', Domain::integer(Field::Long, 11), Fill::none());

        self::assertSame([true, false], [$nullable->nullable(), $required->nullable()]);
    }

    public function testWithDomainAnswersTheColumnWithAnotherDomain(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $column = $table->definition->columns[0];

        self::assertSame([false, 'a'], [$column->withDomain($column->domain->withNullable(false))->nullable(), $column->withDomain($column->domain)->name]);
    }

    public function testWithDefaultAnswersTheColumnWithAnotherDefault(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 3)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertFalse($table->definition->columns[0]->withDefault(Fill::none())->default->declared);
    }

    public function testWithGenerationAnswersAGeneratedColumn(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT AS (a + 1) STORED)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $column = $table->definition->columns[1];

        self::assertSame([true, '(`a` + 1)', false], [$column->stored, $column->expression, $column->default->declared]);
    }
}
