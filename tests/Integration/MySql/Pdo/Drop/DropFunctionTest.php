<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Drop;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class DropFunctionTest extends MySqlIntegrationTestCase
{
    public function testDropFunctionThrowsExceptionInExceptionMode(): void
    {
        $this->rawPdo->exec('CREATE FUNCTION test_func() RETURNS INT DETERMINISTIC RETURN 1');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec('DROP FUNCTION test_func');
    }

    public function testDropFunctionIsIgnoredInIgnoreMode(): void
    {
        $this->rawPdo->exec('CREATE FUNCTION test_func_ignore() RETURNS INT DETERMINISTIC RETURN 1');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec('DROP FUNCTION test_func_ignore');

        $this->assertSame(0, $result);
    }

    public function testDropFunctionTriggersNoticeInNoticeMode(): void
    {
        $this->rawPdo->exec('CREATE FUNCTION test_func_notice() RETURNS INT DETERMINISTIC RETURN 1');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Notice
        ));

        $capturedNotices = [];
        set_error_handler(function (int $errno, string $errstr) use (&$capturedNotices): bool {
            if ($errno === E_USER_NOTICE) {
                $capturedNotices[] = $errstr;
                return true;
            }
            return false;
        }, E_USER_NOTICE);

        try {
            $result = $ztdPdo->exec('DROP FUNCTION test_func_notice');

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('DROP FUNCTION', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
