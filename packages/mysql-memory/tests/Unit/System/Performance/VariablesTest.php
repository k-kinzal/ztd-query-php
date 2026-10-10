<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Performance\Variables;
use MySqlMemory\System\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Variables::class)]
#[Small]
final class VariablesTest extends TestCase
{
    public function testRowsNamesTheVariablesOfATable(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $legacy = $system->find('information_schema', 'TABLES');
        self::assertNotNull($legacy);

        self::assertSame([['VARIABLE_NAME' => 'b', 'VARIABLE_VALUE' => '1'], ['VARIABLE_NAME' => 'a', 'VARIABLE_VALUE' => '2']], Variables::rows([['b', '1'], ['a', '2']], new Reading($s->instance, $reading->connection, $system->catalog->tables[count($system->catalog->tables) - 1], GrammarRelease::MySql847)));
        self::assertSame([['VARIABLE_NAME' => 'A', 'VARIABLE_VALUE' => '2'], ['VARIABLE_NAME' => 'B', 'VARIABLE_VALUE' => '1']], Variables::rows([['b', '1'], ['a', '2']], new Reading($s->instance, $reading->connection, $legacy, GrammarRelease::MySql5651)));
    }

    public function testSystemAnswersTheValuesOfASession(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame([['autocommit', 'ON']], array_values(array_filter(Variables::system($s, false, true, $reading), static fn (array $value): bool => $value[0] === 'autocommit')));
        self::assertSame([], array_values(array_filter(Variables::system($s, false, true, $reading), static fn (array $value): bool => $value[0] === 'max_connections')));
    }

    public function testThreadAnswersTheThreadIdOfASession(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame($s->id + 37, Variables::thread($s, $reading));
    }
}
