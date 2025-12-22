<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\InfoRetrieval;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class ShowCreateProcedureTest extends MySqlIntegrationTestCase
{
    public function testShowCreateProcedureThrowsExceptionInExceptionMode(): void
    {
        $proc = $this->uniqueTableName('test_proc');

        $this->rawPdo->exec("CREATE PROCEDURE `{$proc}`() BEGIN SELECT 1; END");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SHOW CREATE PROCEDURE `{$proc}`");
    }

    public function testShowCreateProcedureIsIgnoredInIgnoreMode(): void
    {
        $proc = $this->uniqueTableName('test_proc');

        $this->rawPdo->exec("CREATE PROCEDURE `{$proc}`() BEGIN SELECT 1; END");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->query("SHOW CREATE PROCEDURE `{$proc}`");

        $this->assertFalse($result);
    }

    public function testShowCreateProcedureTriggersNoticeInNoticeMode(): void
    {
        $proc = $this->uniqueTableName('test_proc');

        $this->rawPdo->exec("CREATE PROCEDURE `{$proc}`() BEGIN SELECT 1; END");

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
            $result = $ztdPdo->query("SHOW CREATE PROCEDURE `{$proc}`");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('SHOW CREATE PROCEDURE', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
