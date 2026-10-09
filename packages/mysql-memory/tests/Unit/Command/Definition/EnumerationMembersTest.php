<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\EnumerationMembers;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumerationMembers::class)]
#[Medium]
final class EnumerationMembersTest extends TestCase
{
    public function testCheckUsesTheCollationAndTrimsTrailingSpaces(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(a ENUM('a ','A'))");

        self::assertSame([['Error', 1291, "Column 'a' has duplicated value 'a' in ENUM"]], $session->diagnostics->conditions);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testCheckNotesDuplicatesInDefinitionOrderOutsideStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET sql_mode=''; CREATE TABLE d.t(a SET('a','A','a'))");

        self::assertSame([['Note', 1291, "Column 'a' has duplicated value 'a' in SET"], ['Note', 1291, "Column 'a' has duplicated value 'A' in SET"]], $session->diagnostics->conditions);
    }

    public function testCheckAcceptsDistinctMembersInABinaryCollationAndOrdinaryTypes(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; CREATE TABLE d.t(a ENUM('a','A') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, b INT)");

        self::assertSame([], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }
}
