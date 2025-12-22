<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Call;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class CallProcedureTest extends MySqlIntegrationTestCase
{
    public function testCallProcedureThrowsExceptionInExceptionMode(): void
    {
        $this->rawPdo->exec('CREATE PROCEDURE test_proc_exception() BEGIN SELECT 1; END');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec('CALL test_proc_exception()');
    }

    public function testCallProcedureIsIgnoredInIgnoreMode(): void
    {
        $this->rawPdo->exec('CREATE PROCEDURE test_proc_ignore() BEGIN SELECT 1; END');

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec('CALL test_proc_ignore()');

        $this->assertSame(0, $result);
    }

    public function testCallProcedureTriggersNoticeInNoticeMode(): void
    {
        $this->rawPdo->exec('CREATE PROCEDURE test_proc_notice() BEGIN SELECT 1; END');

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
            $result = $ztdPdo->exec('CALL test_proc_notice()');

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('CALL test_proc_notice()', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
