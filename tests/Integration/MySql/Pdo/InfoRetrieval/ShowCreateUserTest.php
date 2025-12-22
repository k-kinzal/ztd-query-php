<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\InfoRetrieval;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class ShowCreateUserTest extends MySqlIntegrationTestCase
{
    public function testShowCreateUserThrowsExceptionInExceptionMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SHOW CREATE USER 'root'@'localhost'");
    }

    public function testShowCreateUserIsIgnoredInIgnoreMode(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->query("SHOW CREATE USER 'root'@'localhost'");

        $this->assertFalse($result);
    }

    public function testShowCreateUserTriggersNoticeInNoticeMode(): void
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
            $result = $ztdPdo->query("SHOW CREATE USER 'root'@'localhost'");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('SHOW CREATE USER', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
