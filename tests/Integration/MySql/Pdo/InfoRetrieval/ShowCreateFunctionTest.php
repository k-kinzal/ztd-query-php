<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\InfoRetrieval;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class ShowCreateFunctionTest extends MySqlIntegrationTestCase
{
    public function testShowCreateFunctionThrowsExceptionInExceptionMode(): void
    {
        $func = $this->uniqueTableName('test_func');

        $this->rawPdo->exec("CREATE FUNCTION `{$func}`() RETURNS INT DETERMINISTIC RETURN 1");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->query("SHOW CREATE FUNCTION `{$func}`");
    }

    public function testShowCreateFunctionIsIgnoredInIgnoreMode(): void
    {
        $func = $this->uniqueTableName('test_func');

        $this->rawPdo->exec("CREATE FUNCTION `{$func}`() RETURNS INT DETERMINISTIC RETURN 1");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->query("SHOW CREATE FUNCTION `{$func}`");

        $this->assertFalse($result);
    }

    public function testShowCreateFunctionTriggersNoticeInNoticeMode(): void
    {
        $func = $this->uniqueTableName('test_func');

        $this->rawPdo->exec("CREATE FUNCTION `{$func}`() RETURNS INT DETERMINISTIC RETURN 1");

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
            $result = $ztdPdo->query("SHOW CREATE FUNCTION `{$func}`");

            $this->assertFalse($result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('SHOW CREATE FUNCTION', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
