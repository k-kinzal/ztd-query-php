<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlTransactionStatementParser;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlTransactionStatementParser::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerPostgresTransactionForms')]
    public function testParsesPostgresTransactionForms(string $sql): void
    {
        self::assertNotNull((new PgSqlTransactionStatementParser())->parse($sql));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerPostgresTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin work' => ['BEGIN WORK'],
            'begin transaction' => ['BEGIN TRANSACTION'],
            'start' => ['START TRANSACTION'],
            'commit' => ['COMMIT'],
            'commit work' => ['COMMIT WORK'],
            'commit transaction' => ['COMMIT TRANSACTION'],
            'end' => ['END'],
            'end work' => ['END WORK'],
            'end transaction' => ['END TRANSACTION'],
            'rollback' => ['ROLLBACK'],
            'rollback work' => ['ROLLBACK WORK'],
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

        self::assertNull($parser->parse(''));
        self::assertNull($parser->parse('BEGIN IMMEDIATE'));
        self::assertNull($parser->parse('SAVEPOINT `point`'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT "broken'));
    }

    public function testAppliesUnescapedPostgresSavepointName(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);
        $transactions->begin();
        $statement = (new PgSqlTransactionStatementParser())->parse('SAVEPOINT "a""b"');
        self::assertNotNull($statement);
        $statement->apply($transactions);
        $store->insert('items', [['id' => 2]]);

        $transactions->rollBackTo('a"b');

        self::assertSame([['id' => 1]], $store->get('items'));
    }
    public function testMatchesAnyReportsTheTokensSpellingOneOfTheForms(): void
    {
        $tokens = SqlTokenStream::tokenize('BEGIN', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testMatchesAnyIsFalseWhereTheTokensSpellNoneOfThem(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testNameAfterAnswersTheNameWrittenAfterTheOpening(): void
    {
        $tokens = SqlTokenStream::tokenize('SAVEPOINT sp1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('sp1', (new PgSqlTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testNameAfterIsNothingWhereTheStatementOpensDifferently(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testMatchesReportsTheTokensBeingExactlyThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlTransactionStatementParser())->matches($tokens, ['START', 'TRANSACTION']));
    }

    public function testMatchesIsFalseWhereMoreIsWrittenThanThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlTransactionStatementParser())->matches($tokens, ['START']));
    }

    public function testUnquoteAnswersTheNameAQuotedIdentifierStandsFor(): void
    {
        self::assertSame('order', (new PgSqlTransactionStatementParser())->unquote('"order"'));
    }

    public function testUnquoteLeavesAnUnquotedNameAlone(): void
    {
        self::assertSame('sp1', (new PgSqlTransactionStatementParser())->unquote('sp1'));
    }

    public function testUnquoteIsNothingWhereTheQuotingNeverClosed(): void
    {
        self::assertNull((new PgSqlTransactionStatementParser())->unquote('"order'));
    }
}
