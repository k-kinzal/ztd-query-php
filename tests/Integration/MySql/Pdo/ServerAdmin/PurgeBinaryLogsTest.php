<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\ServerAdmin;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class PurgeBinaryLogsTest extends MySqlIntegrationTestCase
{
    public function testPurgeBinaryLogsThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("PURGE BINARY LOGS BEFORE '2024-01-01'");
    }

    public function testPurgeBinaryLogsIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("PURGE BINARY LOGS BEFORE '2024-01-01'");

        $this->assertSame(0, $result);
    }

    public function testPurgeBinaryLogsTriggersNoticeInNoticeMode(): void
    {
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
            $result = $ztdPdo->exec("PURGE BINARY LOGS BEFORE '2024-01-01'");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('PURGE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
