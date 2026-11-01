<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Storage;

use MySqlMemory\Command\Admin\Storage\LegacyTablespaces;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyTablespaces::class)]
#[Small]
final class LegacyTablespacesTest extends TestCase
{
    public function testExecuteRefusesAlterBeforeLookingUpTheTablespace(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run("ALTER TABLESPACE missing ADD DATAFILE 'x.ibd'");

        self::assertSame([['Error', 1178, "The storage engine for the table doesn't support ALTER TABLESPACE"]], $session->diagnostics->conditions);
    }

    public function testCreateReportsTheLegacyDatafileConditions(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run("CREATE TABLESPACE s ADD DATAFILE 'text'");

        self::assertSame([['Error', 3121, "Incorrect File Name 'text'."], ['Error', 1528, 'Failed to create TABLESPACE s'], ['Error', 1031, "Table storage engine for 's' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testCreatePreservesTheExistingTablespaceConditionChain(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE TABLESPACE s ADD DATAFILE 's.ibd'");
        $session->run("CREATE TABLESPACE s ADD DATAFILE 's.ibd'");

        self::assertSame([['Error', 1813, 'InnoDB: A tablespace named `s` already exists.'], ['Error', 1528, 'Failed to create TABLESPACE s'], ['Error', 1813, "Tablespace 's' exists."]], $session->diagnostics->conditions);
        self::assertArrayHasKey('s', $session->instance->registry->tablespaces);
    }

    public function testDropAcceptsAnExplicitEngineAfterCreation(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE TABLESPACE s ADD DATAFILE 'x.ibd' ENGINE InnoDB; DROP TABLESPACE s ENGINE InnoDB");

        self::assertArrayNotHasKey('s', $session->instance->registry->tablespaces);
        $session->run('DROP TABLESPACE s');
        self::assertSame([['Error', 1529, 'Failed to drop TABLESPACE s'], ['Error', 1812, 'Tablespace is missing for table s.']], $session->diagnostics->conditions);
    }
}
