<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\InnoDbOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(InnoDbOptions::class)]
#[Medium]
final class InnoDbOptionsTest extends TestCase
{
    public function testCheckReportsAllOptionWarningsBeforeTheEngineFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(a INT) COMPRESSION='bad' ROW_FORMAT=FIXED");

        self::assertSame([['Warning', 1478, 'InnoDB: invalid ROW_FORMAT specifier.'], ['Warning', 1112, "InnoDB: Unsupported compression algorithm 'bad'"], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testCheckAcceptsOptionsWhenInnoDbStrictModeIsDisabled(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; SET innodb_strict_mode=0; CREATE TABLE d.t(a INT) ROW_FORMAT=FIXED');

        self::assertSame([['Warning', 1478, 'InnoDB: assuming ROW_FORMAT=DYNAMIC.']], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    public function testEncryptionReportsTheSpecificErrorBeforeTheEngineFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(a INT) ENCRYPTION='bad'");

        self::assertSame([['Error', 3184, 'Invalid encryption option.'], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }
}
