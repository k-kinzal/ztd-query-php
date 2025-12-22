<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateOther;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;

final class CreateSpatialIndexTest extends MySqlIntegrationTestCase
{
    public function testCreateSpatialIndexThrowsExceptionInExceptionMode(): void
    {
        $table = $this->uniqueTableName('locations');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, coords GEOMETRY NOT NULL SRID 4326) ENGINE=InnoDB");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        $this->expectException(UnsupportedSqlException::class);

        $ztdPdo->exec("CREATE SPATIAL INDEX idx_coords ON `{$table}` (coords)");
    }

    public function testCreateSpatialIndexIsIgnoredInIgnoreMode(): void
    {
        $table = $this->uniqueTableName('locations');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, coords GEOMETRY NOT NULL SRID 4326) ENGINE=InnoDB");

        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Ignore
        ));

        $result = $ztdPdo->exec("CREATE SPATIAL INDEX idx_coords ON `{$table}` (coords)");

        $this->assertSame(0, $result);
    }

    public function testCreateSpatialIndexTriggersNoticeInNoticeMode(): void
    {
        $table = $this->uniqueTableName('locations');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, coords GEOMETRY NOT NULL SRID 4326) ENGINE=InnoDB");

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
            $result = $ztdPdo->exec("CREATE SPATIAL INDEX idx_coords ON `{$table}` (coords)");

            $this->assertSame(0, $result);
            $this->assertCount(1, $capturedNotices);
            $this->assertStringContainsString('[ZTD Notice]', $capturedNotices[0]);
            $this->assertStringContainsString('CREATE SPATIAL INDEX', $capturedNotices[0]);
        } finally {
            restore_error_handler();
        }
    }
}
