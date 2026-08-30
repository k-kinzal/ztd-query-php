<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Dialect\SqliteLexerProfile;
use ZtdQuery\Platform\Sqlite\Parse\SqliteTransactionStatementParser;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteTransactionStatementParser::class)]
#[UsesClass(SqliteLexerProfile::class)]
final class SqliteTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerSqliteTransactionForms')]
    public function testParsesSqliteTransactionForms(string $sql): void
    {
        self::assertNotNull((new SqliteTransactionStatementParser())->parse($sql));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSqliteTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin transaction' => ['BEGIN TRANSACTION'],
            'begin deferred' => ['BEGIN DEFERRED'],
            'begin deferred transaction' => ['BEGIN DEFERRED TRANSACTION'],
            'begin immediate' => ['BEGIN IMMEDIATE'],
            'begin immediate transaction' => ['BEGIN IMMEDIATE TRANSACTION'],
            'begin exclusive' => ['BEGIN EXCLUSIVE'],
            'begin exclusive transaction' => ['BEGIN EXCLUSIVE TRANSACTION'],
            'commit' => ['COMMIT'],
            'commit transaction' => ['COMMIT TRANSACTION'],
            'end' => ['END'],
            'end transaction' => ['END TRANSACTION'],
            'rollback' => ['ROLLBACK'],
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

        self::assertNull($parser->parse(''));
        self::assertNull($parser->parse('START TRANSACTION'));
        self::assertNull($parser->parse('COMMIT WORK'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT `broken'));
    }

    public function testAppliesUnescapedSqliteSavepointName(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);
        $transactions->begin();
        $statement = (new SqliteTransactionStatementParser())->parse('SAVEPOINT `a``b`');
        self::assertNotNull($statement);
        $statement->apply($transactions);
        $store->insert('items', [['id' => 2]]);

        $transactions->rollBackTo('a`b');

        self::assertSame([['id' => 1]], $store->get('items'));
    }
    public function testMatchesAnyReportsTheTokensSpellingOneOfTheForms(): void
    {
        $tokens = SqlTokenStream::tokenize('BEGIN', SqliteLexerProfile::create())->significantTokens();

        self::assertTrue((new SqliteTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testMatchesAnyIsFalseWhereTheTokensSpellNoneOfThem(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', SqliteLexerProfile::create())->significantTokens();

        self::assertFalse((new SqliteTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testNameAfterAnswersTheNameWrittenAfterTheOpening(): void
    {
        $tokens = SqlTokenStream::tokenize('SAVEPOINT sp1', SqliteLexerProfile::create())->significantTokens();

        self::assertSame('sp1', (new SqliteTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testNameAfterIsNothingWhereTheStatementOpensDifferently(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', SqliteLexerProfile::create())->significantTokens();

        self::assertNull((new SqliteTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testMatchesReportsTheTokensBeingExactlyThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', SqliteLexerProfile::create())->significantTokens();

        self::assertTrue((new SqliteTransactionStatementParser())->matches($tokens, ['START', 'TRANSACTION']));
    }

    public function testMatchesIsFalseWhereMoreIsWrittenThanThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', SqliteLexerProfile::create())->significantTokens();

        self::assertFalse((new SqliteTransactionStatementParser())->matches($tokens, ['START']));
    }

    public function testUnquoteAnswersTheNameAQuotedIdentifierStandsFor(): void
    {
        self::assertSame('order', (new SqliteTransactionStatementParser())->unquote('"order"'));
    }

    public function testUnquoteLeavesAnUnquotedNameAlone(): void
    {
        self::assertSame('sp1', (new SqliteTransactionStatementParser())->unquote('sp1'));
    }

    public function testUnquoteIsNothingWhereTheQuotingNeverClosed(): void
    {
        self::assertNull((new SqliteTransactionStatementParser())->unquote('"order'));
    }
}
