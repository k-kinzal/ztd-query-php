<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Program\Typed;
use MySqlMemory\System\Reading;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Typed::class)]
#[Small]
final class TypedTest extends TestCase
{
    public function testColumnsDescribesATypeAsWritten(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query("CREATE PROCEDURE pr(IN x INT, OUT y VARCHAR(20) CHARSET latin1, INOUT z DECIMAL(5,2)) COMMENT 'proc' SELECT x INTO y");
        $s->query('CREATE FUNCTION fn(a BIGINT UNSIGNED, s TEXT) RETURNS VARCHAR(30) DETERMINISTIC NO SQL RETURN CONCAT(a, s)');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $routine = $s->instance->dictionary->schema('d')?->procedures['pr'] ?? null;
        self::assertNotNull($routine);

        self::assertSame(['varchar', 20, 20, 'latin1', 'varchar(20)'], array_values(array_intersect_key(Typed::columns($routine, $routine->statement->parameters->parameters[1]->type, null, $reading), array_flip(['DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH', 'CHARACTER_OCTET_LENGTH', 'CHARACTER_SET_NAME', 'DTD_IDENTIFIER']))));
    }

    public function testDescribedDescribesATypeFromItsDomain(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['decimal', 5, 2, 'decimal(5,2)'], array_values(array_intersect_key(Typed::described(Domain::decimal(5, 2), null, $reading), array_flip(['DATA_TYPE', 'NUMERIC_PRECISION', 'NUMERIC_SCALE', 'DTD_IDENTIFIER']))));
    }

    public function testRoutinesAnswersTheRoutinesByName(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query("CREATE PROCEDURE pr(IN x INT, OUT y VARCHAR(20) CHARSET latin1, INOUT z DECIMAL(5,2)) COMMENT 'proc' SELECT x INTO y");
        $s->query('CREATE FUNCTION fn(a BIGINT UNSIGNED, s TEXT) RETURNS VARCHAR(30) DETERMINISTIC NO SQL RETURN CONCAT(a, s)');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['fn', 'pr'], array_map(static fn ($routine): string => $routine->name, Typed::routines($reading)));
    }
}
