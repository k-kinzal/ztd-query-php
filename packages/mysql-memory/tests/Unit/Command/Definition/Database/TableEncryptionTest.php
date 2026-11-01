<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Database;

use MySqlMemory\Command\Definition\Database\TableEncryption;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(TableEncryption::class)]
#[Small]
final class TableEncryptionTest extends TestCase
{
    public function testOperandUsesTheLastExplicitSetting(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $create = $session->analyze("CREATE TABLE d.t(a INT) ENCRYPTION='Y' ENCRYPTION='n'");
        self::assertInstanceOf(CreateTable::class, $create);

        self::assertSame('n', (new TableEncryption())->operand($create));
    }

    public function testInheritedUsesTheDatabaseButExcludesTemporaryTables(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d ENCRYPTION 'Y'; USE d");
        $persistent = $session->analyze('CREATE TABLE t(a INT)');
        $temporary = $session->analyze('CREATE TEMPORARY TABLE t(a INT)');
        self::assertInstanceOf(CreateTable::class, $persistent);
        self::assertInstanceOf(CreateTable::class, $temporary);

        self::assertSame(Encryption::Enabled, (new TableEncryption())->inherited($persistent, $session));
        self::assertSame(Encryption::Disabled, (new TableEncryption())->inherited($temporary, $session));
    }

    public function testValueInheritsAnEmptyPersistentOperand(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d ENCRYPTION 'Y'");
        $create = $session->analyze("CREATE TABLE d.t(a INT) ENCRYPTION=''");
        self::assertInstanceOf(CreateTable::class, $create);

        self::assertSame('Y', (new TableEncryption())->value($create, $session));
    }

    public function testCheckWarnsWhenAPersistentTableOverridesTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d ENCRYPTION 'Y'; CREATE TABLE d.t(a INT) ENCRYPTION='N'");

        self::assertSame([['Warning', 3824, 'Creating an unencrypted table in a database with default encryption enabled.']], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }
}
