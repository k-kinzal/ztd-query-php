<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\LiteralDefaults;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LiteralDefaults::class)]
#[Small]
final class LiteralDefaultsTest extends TestCase
{
    public function testValueRejectsNullInANonNullableColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $this->expectExceptionCode(1067);

        $session->query('CREATE TABLE t(a BLOB NOT NULL DEFAULT NULL)');
    }

    public function testCheckAcceptsNullForNullableLargeColumns(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a BLOB DEFAULT NULL)');
        $session->query('INSERT INTO t VALUES()');

        $result = $session->query('SELECT a FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testCheckRefusesLiteralGeometryDefaultsBeforeStorageConversion(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $this->expectExceptionCode(1101);

        $session->query("CREATE TABLE t(a POINT DEFAULT 'text')");
    }

    public function testCheckKeepsTheSubstitutedDefaultAfterStrictModeIsEnabled(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("SET sql_mode=''; CREATE TABLE t(a BLOB NOT NULL DEFAULT '')");
        $session->query("SET sql_mode='STRICT_TRANS_TABLES'; INSERT INTO t VALUES(DEFAULT)");
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['']], $result->rows);
    }
}
