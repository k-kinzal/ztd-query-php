<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlTransactionStatementParser;

#[CoversClass(PgSqlTransactionStatementParser::class)]
final class PgSqlTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerPostgresTransactionForms')]
    public function testParsesPostgresTransactionForms(string $sql): void
    {
        self::assertNotNull((new PgSqlTransactionStatementParser())->parse($sql));
    }

    /** @return array<string, array{string}> */
    public static function providerPostgresTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin work' => ['BEGIN WORK'],
            'begin transaction' => ['BEGIN TRANSACTION'],
            'start' => ['START TRANSACTION'],
            'commit' => ['COMMIT'],
            'end transaction' => ['END TRANSACTION'],
            'rollback transaction' => ['ROLLBACK TRANSACTION'],
            'savepoint' => ['SAVEPOINT "a""b"'],
            'rollback to' => ['ROLLBACK TO point'],
            'rollback to savepoint' => ['ROLLBACK TO SAVEPOINT point'],
            'release' => ['RELEASE point'],
            'release savepoint' => ['RELEASE SAVEPOINT point'],
        ];
    }

    public function testRejectsNonPostgresAndMalformedForms(): void
    {
        $parser = new PgSqlTransactionStatementParser();

        self::assertNull($parser->parse('BEGIN IMMEDIATE'));
        self::assertNull($parser->parse('SAVEPOINT `point`'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT "broken'));
    }
}
