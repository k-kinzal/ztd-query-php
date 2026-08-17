<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class CopyTest extends TestCase
{
    public function testCopyArrayAndFileMethodsUseShadowDataWithoutTouchingPhysicalTable(): void
    {
        [$schemaName, $pdo] = PostgreSqlContainer::createTestSchema();
        $exportFile = tempnam(sys_get_temp_dir(), 'ztd-copy-export-');
        $importFile = tempnam(sys_get_temp_dir(), 'ztd-copy-import-');
        self::assertNotFalse($exportFile);
        self::assertNotFalse($importFile);

        try {
            $pdo->exec(
                'CREATE TABLE copy_target ('
                . 'id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY, '
                . 'value TEXT NOT NULL, optional TEXT, active BOOLEAN NOT NULL, '
                . 'generated_value TEXT GENERATED ALWAYS AS (upper(value)) STORED)'
            );
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            self::assertTrue($ztdPdo->pgsqlCopyFromArray(
                'copy_target',
                ["1|a\\|b|\\N|t\n", "2|line\\nfeed|text|f\n"],
                '|',
                '\\N',
                'id, value, optional, active',
            ));
            self::assertTrue($ztdPdo->copyFromArray(
                'copy_target',
                new \ArrayIterator(["3|iterator|value|t\n"]),
                '|',
                '\\N',
                'id, value, optional, active',
            ));

            $rows = $ztdPdo->query(
                'SELECT id, value, optional, active, generated_value FROM copy_target ORDER BY id',
            );
            self::assertNotFalse($rows);
            self::assertSame([
                ['id' => 1, 'value' => 'a|b', 'optional' => null, 'active' => true, 'generated_value' => 'A|B'],
                ['id' => 2, 'value' => "line\nfeed", 'optional' => 'text', 'active' => false, 'generated_value' => "LINE\nFEED"],
                ['id' => 3, 'value' => 'iterator', 'optional' => 'value', 'active' => true, 'generated_value' => 'ITERATOR'],
            ], $rows->fetchAll(PDO::FETCH_ASSOC));

            $exported = $ztdPdo->pgsqlCopyToArray(
                'copy_target',
                '|',
                'NULL',
                'id, value, optional, active',
            );
            self::assertSame([
                "1|a\\|b|NULL|t\n",
                "2|line\\nfeed|text|f\n",
                "3|iterator|value|t\n",
            ], $exported);
            self::assertTrue($ztdPdo->copyToFile(
                'copy_target',
                $exportFile,
                '|',
                'NULL',
                'id, value, optional, active',
            ));
            self::assertSame(implode('', $exported), file_get_contents($exportFile));
            self::assertTrue($ztdPdo->pgsqlCopyToFile(
                'copy_target',
                $exportFile,
                '|',
                'NULL',
                'id, value, optional, active',
            ));

            self::assertSame(17, file_put_contents($importFile, "4|from-file|\\N|f\n"));
            self::assertTrue($ztdPdo->pgsqlCopyFromFile(
                'copy_target',
                $importFile,
                '|',
                '\\N',
                'id, value, optional, active',
            ));
            $afterFileImport = $ztdPdo->copyToArray(
                'copy_target',
                fields: 'id, value, optional, active',
            );
            self::assertNotFalse($afterFileImport);
            self::assertSame(["4\tfrom-file\t\\N\tf\n"], array_slice($afterFileImport, -1));
            self::assertSame(0, file_put_contents($importFile, ''));
            self::assertTrue($ztdPdo->copyFromFile(
                'copy_target',
                $importFile,
                fields: 'id, value, optional, active',
            ));

            $physical = $pdo->query('SELECT COUNT(*) FROM copy_target');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            unlink($exportFile);
            unlink($importFile);
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testRawCopyIsRejectedExplicitlyAndMalformedRowsAreAtomic(): void
    {
        [$schemaName, $pdo] = PostgreSqlContainer::createTestSchema();

        try {
            $pdo->exec('CREATE TABLE copy_target (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            try {
                $ztdPdo->exec('COPY copy_target FROM STDIN');
                self::fail('Expected raw COPY to be rejected.');
            } catch (ZtdPdoException $exception) {
                self::assertStringContainsString('pgsqlCopyFromArray()', $exception->getMessage());
            }

            try {
                $ztdPdo->pgsqlCopyFromArray('copy_target', ["1\tvalid\n", "2\n"]);
                self::fail('Expected a malformed COPY row to be rejected.');
            } catch (\ValueError $exception) {
                self::assertStringContainsString('2 fields are required', $exception->getMessage());
            }

            $shadow = $ztdPdo->query('SELECT * FROM copy_target');
            self::assertNotFalse($shadow);
            self::assertSame([], $shadow->fetchAll(PDO::FETCH_ASSOC));
            $physical = $pdo->query('SELECT COUNT(*) FROM copy_target');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
