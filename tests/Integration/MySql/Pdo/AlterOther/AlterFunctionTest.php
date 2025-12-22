<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterOther;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class AlterFunctionTest extends MySqlIntegrationTestCase
{
    public function testAlterFunctionThrowsExceptionInExceptionMode(): void
    {
        $this->rawPdo->exec('CREATE FUNCTION test_func() RETURNS INT DETERMINISTIC RETURN 1');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec('ALTER FUNCTION test_func COMMENT "Test"');
    }

    public function testAlterFunctionIsIgnoredInIgnoreMode(): void
    {
        $this->rawPdo->exec('CREATE FUNCTION test_func_ignore() RETURNS INT DETERMINISTIC RETURN 1');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec('ALTER FUNCTION test_func_ignore COMMENT "Test"');

        $this->assertSame(0, $result);
    }

    public function testAlterFunctionTriggersNoticeInNoticeMode(): void
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
            $result = $ztdPdo->exec('ALTER FUNCTION test_func_notice COMMENT "Test"');

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('ALTER FUNCTION', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
