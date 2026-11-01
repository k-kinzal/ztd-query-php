<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Reading;
use MySqlMemory\System\Schema\Schemata;
use MySqlMemory\System\SystemRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(SystemRows::class)]
#[Small]
final class SystemRowsTest extends TestCase
{
    public function testRowsAnswersEachRowByColumnName(): void
    {
        $s = (new Instance())->connect();
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $rows = (new Schemata())->rows($reading);

        self::assertSame(['CATALOG_NAME' => 'def', 'SCHEMA_NAME' => 'information_schema'], array_slice($rows[0], 0, 2));
    }
}
