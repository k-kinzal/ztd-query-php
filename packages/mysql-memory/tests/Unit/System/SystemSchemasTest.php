<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemSchemas;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(SystemSchemas::class)]
#[Small]
final class SystemSchemasTest extends TestCase
{
    public function testDeclarationsDeclareTheSystemTablesOfTheRelease(): void
    {
        $system = (new Instance('5.7.44'))->dictionary->system;
        self::assertNotNull($system);

        self::assertCount(180, $system->declarations());
    }

    public function testTableFindsTheSystemTableOfADeclaration(): void
    {
        $system = (new Instance())->dictionary->system;
        self::assertNotNull($system);
        $tables = $system->find('information_schema', 'tables');
        self::assertNotNull($tables);

        self::assertSame($tables, $system->table($tables->declaration));
    }

    public function testFindFindsATableByName(): void
    {
        $system = (new Instance())->dictionary->system;
        self::assertNotNull($system);

        self::assertSame(['SCHEMATA', null], [$system->find('INFORMATION_SCHEMA', 'Schemata')?->name, $system->find('mysql', 'USER')]);
    }

    public function testDefinitionAnswersTheColumnsOfASystemTable(): void
    {
        $system = (new Instance())->dictionary->system;
        self::assertNotNull($system);
        $table = $system->find('information_schema', 'SCHEMATA');
        self::assertNotNull($table);

        self::assertSame(['SCHEMATA', 6, false], [$system->definition($table)->name, count($system->definition($table)->columns), $system->definition($table)->columns[1]->nullable()]);
        self::assertSame($system->definition($table), $system->definition($table));
    }

    public function testReadAnswersTheRowsATableHoldsNow(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $table = $system->find('information_schema', 'SCHEMATA');
        self::assertNotNull($table);

        self::assertSame(['def', 'd', 'utf8mb4', 'utf8mb4_0900_ai_ci', null, 'NO'], $system->read($table, $reading->connection)->data->rows[1]);
    }

    public function testRowsAnswersNoRowForATableWithoutASource(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $table = $system->find('performance_schema', 'threads');
        self::assertNotNull($table);

        self::assertSame([], $system->rows($table, $reading->connection));
    }

    public function testHeldHoldsAValueAsTheDomainSays(): void
    {
        self::assertSame([3, 1.5, '7', null], [SystemSchemas::held('3', Domain::integer()), SystemSchemas::held(1.5, Domain::double()), SystemSchemas::held(7, Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))), SystemSchemas::held(null, Domain::integer())]);
    }

    public function testOriginAnswersTheColumnAResultReports(): void
    {
        $system = (new Instance())->dictionary->system;
        self::assertNotNull($system);
        $table = $system->find('information_schema', 'TABLES');
        self::assertNotNull($table);
        $origin = $system->origin($system->definition($table), 4, 't');

        self::assertSame(['', 't', 'TABLES', 'ENGINE', 0, true], [$origin?->schema, $origin?->table, $origin?->originalTable, $origin?->column, $origin?->flags, $origin?->exact]);
    }

    public function testNamedTellsWhetherAResultNamesAColumnAsTheTableDoes(): void
    {
        $system = (new Instance())->dictionary->system;
        $legacy = (new Instance('5.7.44'))->dictionary->system;
        self::assertNotNull($system);
        self::assertNotNull($legacy);
        $tables = $system->find('information_schema', 'TABLES');
        $user = $system->find('mysql', 'user');
        $old = $legacy->find('information_schema', 'TABLES');
        self::assertNotNull($tables);
        self::assertNotNull($user);
        self::assertNotNull($old);

        self::assertSame([true, false, false], [$system->named($tables->declaration), $system->named($user->declaration), $legacy->named($old->declaration)]);
    }

    public function testReadNamesTheColumnsOfAnInformationSchemaViewAsItDoes(): void
    {
        $s = (new Instance())->connect();
        $legacy = (new Instance('5.7.44'))->connect();

        $result1 = $s->query('SELECT schema_name FROM information_schema.schemata')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $result2 = $legacy->query('SELECT schema_name FROM information_schema.schemata')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame(['SCHEMA_NAME', 'schema_name'], [$result1->columns[0]->name, $result2->columns[0]->name]);
    }
}
