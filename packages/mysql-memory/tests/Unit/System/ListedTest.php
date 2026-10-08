<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Listed::class)]
#[Small]
final class ListedTest extends TestCase
{
    public function testSchemasAnswersTheDatabasesInTheirOrder(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['d', 'information_schema', 'mysql', 'performance_schema', 'sys'], array_map(static fn ($schema): string => $schema->name, Listed::schemas($reading)));
        self::assertSame(['mysql', 'information_schema', 'performance_schema', 'sys', 'd'], array_map(static fn ($schema): string => $schema->name, Listed::schemas($reading, true)));
    }

    public function testOfAnswersTheTablesViewsAndSystemTablesOfADatabase(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE b (a INT)');
        $s->query('CREATE TABLE a (a INT)');
        $s->query('CREATE TEMPORARY TABLE c (a INT)');
        $s->query('CREATE VIEW v AS SELECT 1');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $schema = $s->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame([['a', 'b', 'v'], ['b', 'a', 'v']], [array_keys(Listed::of($schema, $reading)), array_keys(Listed::of($schema, $reading, true))]);
    }

    public function testStoredAnswersTheColumnsOfAView(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE VIEW v AS SELECT 1 AS x');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $view = $s->instance->dictionary->schema('d')->views['v'] ?? null;
        self::assertNotNull($view);

        self::assertSame('x', Listed::stored($view, $reading)->definition->columns[0]->name);
    }
}
