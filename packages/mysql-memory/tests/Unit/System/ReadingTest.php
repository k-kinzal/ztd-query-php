<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Reading::class)]
#[Small]
final class ReadingTest extends TestCase
{
    public function testSessionAnswersTheSessionThatReads(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame($s, $reading->session());
    }

    public function testSessionsAnswersTheOpenSessions(): void
    {
        $instance = new Instance();
        $closed = $instance->connect();
        $closed->close();
        $s = $instance->connect();
        $system = $instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame([$s->id => $s], $reading->sessions());
    }

    public function testDictionaryTellsWhetherInformationSchemaIsMadeOfViews(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertTrue($reading->dictionary());
        self::assertFalse((new Reading($s->instance, $reading->connection, $reading->table, GrammarRelease::MySql5744))->dictionary());
    }
}
