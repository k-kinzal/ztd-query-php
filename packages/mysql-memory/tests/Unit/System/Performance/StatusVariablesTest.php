<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\System\Performance\StatusVariables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(StatusVariables::class)]
#[Small]
final class StatusVariablesTest extends TestCase
{
    public function testOfReadsTheCatalogOfARelease(): void
    {
        self::assertSame(StatusVariables::of(GrammarRelease::MySql847), StatusVariables::of(GrammarRelease::MySql847));
        self::assertSame(['Aborted_clients', 'Global', '0', true], StatusVariables::of(GrammarRelease::MySql847)->entries[0]);
    }

    public function testValuesAnswersTheValuesOfAScope(): void
    {
        $instance = new Instance();
        $instance->connect();
        $catalog = StatusVariables::of(GrammarRelease::MySql847);

        self::assertSame([['Connections', '1'], ['Threads_connected', '3']], array_values(array_filter($catalog->values($instance, true, false, 3), static fn (array $value): bool => in_array($value[0], ['Connections', 'Threads_connected'], true))));
        self::assertSame([[], [['Com_select', '0']]], [array_values(array_filter($catalog->values($instance, true, false, 3), static fn (array $value): bool => $value[0] === 'Com_select')), array_values(array_filter($catalog->values($instance, false, false, 3, false), static fn (array $value): bool => $value[0] === 'Com_select'))]);
        self::assertSame([], array_values(array_filter($catalog->values($instance, false, true, 3), static fn (array $value): bool => $value[0] === 'Uptime')));
    }
}
