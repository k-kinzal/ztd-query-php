<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Parse\MySqlTransactionStatementParser;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlTransactionStatementParser::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerMySqlTransactionForms')]
    public function testParsesMySqlTransactionForms(string $sql): void
    {
        self::assertNotNull((new MySqlTransactionStatementParser())->parse($sql));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerMySqlTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin work' => ['BEGIN WORK'],
            'start' => ['START TRANSACTION'],
            'commit' => ['COMMIT'],
            'commit work' => ['COMMIT WORK'],
            'rollback' => ['ROLLBACK'],
            'rollback work' => ['ROLLBACK WORK'],
            'savepoint' => ['SAVEPOINT `a``b`'],
            'rollback to' => ['ROLLBACK TO `a``b`'],
            'rollback to savepoint' => ['ROLLBACK TO SAVEPOINT point'],
            'release' => ['RELEASE SAVEPOINT point'],
        ];
    }

    public function testRejectsNonMySqlAndMalformedForms(): void
    {
        $parser = new MySqlTransactionStatementParser();

        self::assertNull($parser->parse(''));
        self::assertNull($parser->parse('BEGIN IMMEDIATE'));
        self::assertNull($parser->parse('END TRANSACTION'));
        self::assertNull($parser->parse('RELEASE point'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT `broken'));
    }

    public function testAppliesUnescapedMySqlSavepointName(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);
        $transactions->begin();
        $statement = (new MySqlTransactionStatementParser())->parse('SAVEPOINT `a``b`');
        self::assertNotNull($statement);
        $statement->apply($transactions);
        $store->insert('items', [['id' => 2]]);

        $transactions->rollBackTo('a`b');

        self::assertSame([['id' => 1]], $store->get('items'));
    }
    public function testMatchesAnyReportsTheTokensSpellingOneOfTheForms(): void
    {
        $tokens = SqlTokenStream::tokenize('BEGIN', MySqlLexerProfile::create())->significantTokens();

        self::assertTrue((new MySqlTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testMatchesAnyIsFalseWhereTheTokensSpellNoneOfThem(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', MySqlLexerProfile::create())->significantTokens();

        self::assertFalse((new MySqlTransactionStatementParser())->matchesAny($tokens, [['START'], ['BEGIN']]));
    }

    public function testNameAfterAnswersTheNameWrittenAfterTheOpening(): void
    {
        $tokens = SqlTokenStream::tokenize('SAVEPOINT sp1', MySqlLexerProfile::create())->significantTokens();

        self::assertSame('sp1', (new MySqlTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testNameAfterIsNothingWhereTheStatementOpensDifferently(): void
    {
        $tokens = SqlTokenStream::tokenize('COMMIT', MySqlLexerProfile::create())->significantTokens();

        self::assertNull((new MySqlTransactionStatementParser())->nameAfter($tokens, [['SAVEPOINT']]));
    }

    public function testMatchesReportsTheTokensBeingExactlyThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', MySqlLexerProfile::create())->significantTokens();

        self::assertTrue((new MySqlTransactionStatementParser())->matches($tokens, ['START', 'TRANSACTION']));
    }

    public function testMatchesIsFalseWhereMoreIsWrittenThanThoseKeywords(): void
    {
        $tokens = SqlTokenStream::tokenize('START TRANSACTION', MySqlLexerProfile::create())->significantTokens();

        self::assertFalse((new MySqlTransactionStatementParser())->matches($tokens, ['START']));
    }

    public function testUnquoteAnswersTheNameAQuotedIdentifierStandsFor(): void
    {
        self::assertSame('order', (new MySqlTransactionStatementParser())->unquote('`order`', ['`']));
    }

    public function testUnquoteLeavesAnUnquotedNameAlone(): void
    {
        self::assertSame('sp1', (new MySqlTransactionStatementParser())->unquote('sp1', ['`']));
    }

    public function testUnquoteIsNothingWhereTheQuotingNeverClosed(): void
    {
        self::assertNull((new MySqlTransactionStatementParser())->unquote('`order', ['`']));
    }

}
