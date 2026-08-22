<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteTransactionStatementParser;

#[CoversClass(SqliteTransactionStatementParser::class)]
final class SqliteTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerSqliteTransactionForms')]
    public function testParsesSqliteTransactionForms(string $sql): void
    {
        self::assertNotNull((new SqliteTransactionStatementParser())->parse($sql));
    }

    /** @return array<string, array{string}> */
    public static function providerSqliteTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin transaction' => ['BEGIN TRANSACTION'],
            'begin deferred' => ['BEGIN DEFERRED'],
            'begin immediate' => ['BEGIN IMMEDIATE TRANSACTION'],
            'begin exclusive' => ['BEGIN EXCLUSIVE'],
            'commit transaction' => ['COMMIT TRANSACTION'],
            'end' => ['END'],
            'rollback transaction' => ['ROLLBACK TRANSACTION'],
            'savepoint' => ['SAVEPOINT `a``b`'],
            'rollback to' => ['ROLLBACK TO point'],
            'rollback to savepoint' => ['ROLLBACK TO SAVEPOINT point'],
            'release' => ['RELEASE point'],
            'release savepoint' => ['RELEASE SAVEPOINT point'],
        ];
    }

    public function testRejectsNonSqliteAndMalformedForms(): void
    {
        $parser = new SqliteTransactionStatementParser();

        self::assertNull($parser->parse('START TRANSACTION'));
        self::assertNull($parser->parse('COMMIT WORK'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT `broken'));
    }
}
