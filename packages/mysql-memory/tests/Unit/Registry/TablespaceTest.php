<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Instance;
use MySqlMemory\Registry\Tablespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tablespace::class)]
#[Small]
final class TablespaceTest extends TestCase
{
    public function testActiveIsTrueForANewTablespace(): void
    {
        $tablespace = new Tablespace('ts', true, 'ts.ibu');

        self::assertSame(['ts', true, 'ts.ibu', true], [$tablespace->name, $tablespace->undo, $tablespace->file, $tablespace->active]);
    }

    public function testFileIsTheOneCreateTablespaceNames(): void
    {
        $instance = new Instance();
        $instance->connect()->query("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'");

        self::assertSame(['ts', false, 'ts.ibd'], [$instance->registry->tablespaces['ts']->name ?? null, $instance->registry->tablespaces['ts']->undo ?? null, $instance->registry->tablespaces['ts']->file ?? null]);
    }
}
