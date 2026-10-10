<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use Fuzz\Target\StatementIdentities;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class StatementIdentitiesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerStatements(): iterable
    {
        yield 'plain' => ['SHOW VARIABLES', '8.4.7', true];
        yield 'explicit session' => ['SHOW SESSION VARIABLES', '8.0.44', true];
        yield 'local scope' => [" show\nlocal variables ; ", '9.1.0', true];
        yield 'global scope' => ['SHOW GLOBAL VARIABLES', '8.4.7', false];
        yield 'legacy' => ['SHOW VARIABLES', '5.7.44', false];
        yield 'filtered' => ["SHOW VARIABLES LIKE 'statement_id'", '8.4.7', false];
        yield 'multiple statements' => ['SHOW VARIABLES; SELECT 1', '8.4.7', false];
    }

    #[DataProvider('providerStatements')]
    public function testHandlesOnlyPlainSessionListings(string $sql, string $version, bool $handled): void
    {
        self::assertSame($handled, StatementIdentities::handles($sql, $version));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function providerInvalidRows(): iterable
    {
        foreach (['0', '99', '100', '110', '111', '-1', '1e2', '101.0', '0101', '999999999999999999999', null, 101] as $id) {
            yield 'invalid statement ' . var_export($id, true) => [[['statement_id', $id], ['pseudo_thread_id', '7']]];
        }
        yield 'another connection' => [[['statement_id', '101'], ['pseudo_thread_id', '8']]];
        yield 'missing identity' => [[['statement_id', '101']]];
        yield 'duplicate identity' => [[['statement_id', '101'], ['statement_id', '102'], ['pseudo_thread_id', '7']]];
        yield 'extra field' => [[['statement_id', '101', 'extra'], ['pseudo_thread_id', '7']]];
        yield 'non-row' => [['statement_id', '101']];
    }

    /**
     * @param array<mixed> $rows
     */
    #[DataProvider('providerInvalidRows')]
    public function testPositionsRejectsMalformedOrUnrelatedIdentities(array $rows): void
    {
        $observation = ['results' => [['columns' => [['Variable_name', 'session_variables'], ['Value', 'session_variables']], 'rows' => $rows]]];
        $contract = new StatementIdentities(100, 7);

        self::assertNull($contract->positions($rows, 110));
        self::assertSame($observation, $contract->validated($observation, 110));
    }

    public function testValidatedKeepsMetadataAndEveryUnrelatedValue(): void
    {
        $columns = [['Variable_name', 'session_variables', 'VAR_STRING', 256, 0, ['not_null']], ['Value', 'session_variables', 'VAR_STRING', 4096, 0, []]];
        $observation = ['results' => [['columns' => $columns, 'rows' => [['statement_id', '101'], ['pseudo_thread_id', '7'], ['last_insert_id', '42']]]], 'warnings' => [], 'tables' => ['t1' => [[1]]]];
        $result = (new StatementIdentities(100, 7))->validated($observation, 110);

        $observation['results'][0]['rows'][0][1] = '{' . StatementIdentities::CONTRACT . ':statement_id}';
        $observation['results'][0]['rows'][1][1] = '{' . StatementIdentities::CONTRACT . ':pseudo_thread_id}';
        $observation['contracts'] = [StatementIdentities::CONTRACT];
        self::assertSame($observation, $result);
    }

    public function testValidatedRejectsAnErrorOrUnrelatedShape(): void
    {
        $contract = new StatementIdentities(100, 7);
        $error = ['error' => [1234], 'results' => []];
        $wrongTable = ['results' => [['columns' => [['Variable_name', 'global_variables'], ['Value', 'global_variables']], 'rows' => [['statement_id', '101'], ['pseudo_thread_id', '7']]]]];

        self::assertSame($error, $contract->validated($error, 110));
        self::assertSame($wrongTable, $contract->validated($wrongTable, 110));
        self::assertSame(['results' => []], $contract->validated(['results' => []], 110));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerListings(): iterable
    {
        if (str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'legacy fixed identity' => ["SET pseudo_thread_id=42; SHOW VARIABLES LIKE 'pseudo_thread_id'", []];

            return;
        }
        foreach (['SHOW VARIABLES', 'SHOW SESSION VARIABLES', 'SHOW LOCAL VARIABLES'] as $sql) {
            yield $sql => [$sql, [StatementIdentities::CONTRACT]];
        }
    }

    /**
     * @param list<string> $contracts
     */
    #[DataProvider('providerListings')]
    public function testComparableChecksBothServersIndependently(string $sql, array $contracts): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
        self::assertSame($contracts, $result->contracts);
    }
}
