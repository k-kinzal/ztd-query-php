<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Variable\Persistence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Persistence::class)]
#[Small]
final class PersistenceTest extends TestCase
{
    public function testAssignPersistsWithoutChangingTheRunningValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET PERSIST_ONLY autocommit=0');
        $read = $session->query('SELECT @@GLOBAL.autocommit, @@SESSION.autocommit')[0];

        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['1', '1']], $read->rows);
        self::assertSame(['autocommit' => 'OFF'], $session->variables->globals->persisted);
    }

    public function testAssignPersistsAndChangesTheGlobalValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET PERSIST autocommit=0');
        $read = $session->query('SELECT @@GLOBAL.autocommit, @@SESSION.autocommit')[0];

        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['0', '1']], $read->rows);
        self::assertSame(['autocommit' => 'OFF'], $session->variables->globals->persisted);
    }

    public function testAssignRollsBackSavedConfigurationWhenAnotherValueFails(): void
    {
        $session = (new Instance())->connect();
        $session->run('SET PERSIST autocommit=0, max_connections=NULL');
        $read = $session->query('SELECT @@GLOBAL.autocommit')[0];

        self::assertSame([], $session->variables->globals->persisted);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['1']], $read->rows);
    }
}
