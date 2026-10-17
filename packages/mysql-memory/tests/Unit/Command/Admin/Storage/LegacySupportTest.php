<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Storage;

use MySqlMemory\Command\Admin\Storage\LegacySupport;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacySupport::class)]
#[Small]
final class LegacySupportTest extends TestCase
{
    public function testEngineResolvesTheDefaultForA57Tablespace(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET sql_mode='', default_storage_engine=InnoDB; CREATE TABLESPACE s ADD DATAFILE 'x.ibd' ENGINE unknown_engine");

        self::assertArrayHasKey('s', $session->instance->registry->tablespaces);
        self::assertSame([['Warning', 1286, "Unknown storage engine 'unknown_engine'"]], $session->diagnostics->conditions);
    }

    public function testCheckUsesTheSessionDefaultBeforeLookingUpATablespace(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('SET default_storage_engine=MyISAM');
        $session->run("ALTER TABLESPACE missing ADD DATAFILE 'text'");

        self::assertSame([['Error', 1478, "Table storage engine 'MyISAM' does not support the create option 'TABLESPACE or LOGFILE GROUP'"]], $session->diagnostics->conditions);
    }

    public function testCheckWarnsAndSubstitutesAnUnknownEngineWhenPermitted(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("SET sql_mode='', default_storage_engine=MEMORY");
        $session->run('DROP LOGFILE GROUP g ENGINE=unknown_engine');

        self::assertSame([['Warning', 1286, "Unknown storage engine 'unknown_engine'"], ['Error', 1478, "Table storage engine 'MEMORY' does not support the create option 'TABLESPACE or LOGFILE GROUP'"]], $session->diagnostics->conditions);
    }

    public function testCheckRejectsAnUnknownEngineWhenSubstitutionIsDisabled(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("SET sql_mode='NO_ENGINE_SUBSTITUTION'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $session->query("CREATE TABLESPACE s ADD DATAFILE 'text' ENGINE=unknown_engine");
    }

    public function testCheckLeavesModernStorageExecutionAvailable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE s');

        self::assertArrayHasKey('s', $session->instance->registry->tablespaces);
    }
}
