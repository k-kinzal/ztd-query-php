<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlWithPrefix::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlWithPrefixTest extends TestCase
{
    public function testParsesRecursiveMaterializationAndNestedCteBodies(): void
    {
        $sql = 'WITH RECURSIVE "FIRST"(id) AS MATERIALIZED (SELECT (1)), second AS NOT MATERIALIZED (SELECT id FROM "FIRST") DELETE FROM target';

        self::assertSame(['first', 'second'], (new PgSqlWithPrefix())->declaredCteNames($sql));
        self::assertSame('DELETE FROM target', (new PgSqlWithPrefix())->statementSql($sql));
    }

    public function testHandlesNonHeadersIncompleteHeadersAndEmptyInput(): void
    {

        self::assertSame([], (new PgSqlWithPrefix())->declaredCteNames(''));
        self::assertSame('SELECT 1', (new PgSqlWithPrefix())->statementSql('SELECT 1'));
        self::assertSame([], (new PgSqlWithPrefix())->declaredCteNames('WITH only_name'));
        self::assertSame(
            'WITH x AS (SELECT 1)',
            (new PgSqlWithPrefix())->statementSql('WITH x AS (SELECT 1)'),
        );
        self::assertSame([], (new PgSqlWithPrefix())->declaredCteNames('WITH x AS'));
        self::assertSame([], (new PgSqlWithPrefix())->declaredCteNames('WITH x AS NOT'));
        self::assertSame([], (new PgSqlWithPrefix())->declaredCteNames('WITH x AS (SELECT 1'));
        self::assertSame(
            ['first'],
            (new PgSqlWithPrefix())->declaredCteNames('WITH first AS (SELECT 1), broken'),
        );
    }

    public function testUnquotesPostgreSqlCteIdentifiersAndRejectsForeignQuotes(): void
    {

        self::assertSame([''], (new PgSqlWithPrefix())->declaredCteNames('WITH "" AS (SELECT 1) SELECT 1'));
        self::assertSame(
            ['a"b'],
            (new PgSqlWithPrefix())->declaredCteNames(
                'WITH "a""b" AS (SELECT 1), `c``d` AS (SELECT 2) SELECT 1',
            ),
        );
    }

    public function testStatementSqlAnswersTheStatementWithoutItsPrefix(): void
    {
        self::assertSame(
            'SELECT * FROM x',
            (new PgSqlWithPrefix())->statementSql('WITH x AS (SELECT 1) SELECT * FROM x'),
        );
    }

    public function testStatementSqlLeavesAStatementThatBeginsWithNoPrefix(): void
    {
        self::assertSame('SELECT 1', (new PgSqlWithPrefix())->statementSql('SELECT 1'));
    }

    public function testParseHeaderAnswersWhereTheStatementBeginsAndWhatThePrefixNamed(): void
    {
        $header = (new PgSqlWithPrefix())->parseHeader('WITH a AS (SELECT 1) SELECT 2');

        self::assertSame(['a'], $header['names']);
        self::assertIsInt($header['statementOffset']);
    }

    public function testDeclaredCteNamesAnswersEveryNameThePrefixDeclares(): void
    {
        self::assertSame(
            ['a', 'b'],
            (new PgSqlWithPrefix())->declaredCteNames('WITH a AS (SELECT 1), b AS (SELECT 2) SELECT 3'),
        );
    }

    public function testCarryPrefixKeepsTheCallersOwnPrefixInFrontOfARewrite(): void
    {
        $carried = (new PgSqlWithPrefix())->carryPrefix(
            'WITH a AS (SELECT 1) SELECT * FROM a',
            'WITH s AS (SELECT 2) SELECT * FROM s',
        );

        self::assertStringContainsString('a AS', $carried);
        self::assertStringContainsString('s AS', $carried);
    }

    public function testFindAsIndexStopsAtTheFirstNameItPassesOnTheWay(): void
    {
        $tokens = SqlTokenStream::tokenize('a AS (SELECT 1)', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(1, (new PgSqlWithPrefix())->findAsIndex($tokens, 1));
        self::assertNull((new PgSqlWithPrefix())->findAsIndex($tokens, 0));
    }

    public function testIsSymbolAnswersWhetherATokenIsTheOneWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('(a)', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlWithPrefix())->isSymbol($tokens[0], '('));
        self::assertFalse((new PgSqlWithPrefix())->isSymbol($tokens[0], ')'));
        self::assertFalse((new PgSqlWithPrefix())->isSymbol(null, '('));
    }

    public function testIdentifierNameAnswersTheNameATokenWrites(): void
    {
        $tokens = SqlTokenStream::tokenize('users "Mixed" 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('users', (new PgSqlWithPrefix())->identifierName($tokens[0]));
        self::assertSame('Mixed', (new PgSqlWithPrefix())->identifierName($tokens[1]));
        self::assertNull((new PgSqlWithPrefix())->identifierName($tokens[2]));
    }

    public function testReferencesIdentifierAnswersWhetherAStatementNamesIt(): void
    {
        $prefix = new PgSqlWithPrefix();

        self::assertTrue($prefix->referencesIdentifier('SELECT * FROM users', 'USERS'));
        self::assertFalse($prefix->referencesIdentifier('SELECT * FROM users', 'orders'));
    }

    public function testReferencesAnyIdentifierAnswersWhetherAStatementNamesOneOfThem(): void
    {
        $prefix = new PgSqlWithPrefix();

        self::assertTrue($prefix->referencesAnyIdentifier('SELECT * FROM users', ['orders', 'users']));
        self::assertFalse($prefix->referencesAnyIdentifier('SELECT * FROM users', ['orders']));
    }

    public function testTopLevelTokensLeavesWhatTheStatementNests(): void
    {
        $tokens = (new PgSqlWithPrefix())->topLevelTokens('SELECT (1 + 2)');

        self::assertSame(['SELECT', '(', ')'], array_map(static fn ($t): string => $t->text, $tokens));
    }

    public function testBodyIndexPassesOverTheColumnListAndTheAsAndWhatFollowsIt(): void
    {
        $prefix = new PgSqlWithPrefix();
        $tokens = $prefix->topLevelTokens('WITH c AS NOT MATERIALIZED () SELECT 1');

        self::assertSame(5, $prefix->bodyIndex($tokens, 2));
    }

    public function testPrefixOfAnswersNothingForAStatementThatOpensWithNoPrefix(): void
    {
        self::assertNull((new PgSqlWithPrefix())->prefixOf('SELECT 1'));
    }

    public function testPrefixOfTakesThePrefixApart(): void
    {
        $prefix = (new PgSqlWithPrefix())->prefixOf('WITH c AS (SELECT 1) SELECT * FROM c');

        self::assertSame(
            ['', false, 'c AS (SELECT 1)', 'SELECT * FROM c'],
            [$prefix['leading'] ?? null, $prefix['recursive'] ?? null, $prefix['body'] ?? null, $prefix['tail'] ?? null],
        );
    }

    public function testPrefixOfSaysWhenThePrefixRecurses(): void
    {
        $prefix = (new PgSqlWithPrefix())->prefixOf('WITH RECURSIVE c AS (SELECT 1) SELECT * FROM c');

        self::assertTrue($prefix['recursive'] ?? false);
    }
}
