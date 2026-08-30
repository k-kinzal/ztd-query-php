<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlReturningProjectionParser;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlReturningProjectionParser::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlReturningProjectionParserTest extends TestCase
{
    public function testParsesPostgresQualifiedQuotedAliasesAndWildcard(): void
    {
        $projection = (new PgSqlReturningProjectionParser())->parse(
            'UPDATE users SET name = \'x\' RETURNING users.id AS original_id, *, "name" AS display_name',
        );
        self::assertNotNull($projection);
        self::assertSame([
            ['source' => 'id', 'output' => 'original_id'],
            ['source' => null, 'output' => null],
            ['source' => 'name', 'output' => 'display_name'],
        ], $projection->items());

        self::assertSame([
            ['original_id' => 1, 'id' => 1, 'name' => 'Alice', 'display_name' => 'Alice'],
        ], $projection->project([['id' => 1, 'name' => 'Alice']]));
    }

    public function testUnquotesEscapedPostgresIdentifiersAndStatementTerminator(): void
    {
        $projection = (new PgSqlReturningProjectionParser())->parse(
            'INSERT INTO users VALUES (1) RETURNING "source""name" AS "output""name";',
        );
        self::assertNotNull($projection);

        self::assertSame([['output"name' => 'value']], $projection->project([['source"name' => 'value']]));
    }

    public function testReturnsNullWithoutPostgresReturningClause(): void
    {
        self::assertNull((new PgSqlReturningProjectionParser())->parse('INSERT INTO users VALUES (1)'));
    }

    #[DataProvider('providerInvalidPostgresReturningProjection')]
    public function testRejectsMalformedPostgresProjection(string $projection): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlReturningProjectionParser())->parse('INSERT INTO users VALUES (1) RETURNING ' . $projection);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerInvalidPostgresReturningProjection(): array
    {
        return [
            'empty' => [''],
            'expression' => ['id + 1'],
            'missing alias' => ['id AS'],
            'invalid alias token' => ['id AS +'],
            'tokens after alias' => ['id AS alias extra'],
            'wildcard alias' => ['* AS all_columns'],
            'leading dot' => ['.id'],
            'trailing dot' => ['users.'],
            'double dot' => ['users..id'],
            'non-dot separator' => ['users + id'],
            'leading wildcard path' => ['*.id'],
            'non-trailing wildcard' => ['users.*.id'],
            'non-wildcard symbol path' => ['users.+'],
            'empty quoted identifier' => ['""'],
            'backtick identifier' => ['`id`'],
            'number token' => ['12'],
            'string token' => ["'id'"],
            'unterminated double quote' => ['"id'],
        ];
    }
    public function testParseItemReadsTheColumnAnEntryNames(): void
    {
        self::assertSame(
            ['source' => 'id', 'output' => null],
            (new PgSqlReturningProjectionParser())->parseItem('id'),
        );
    }

    public function testParseItemReadsTheNameAnEntryIsGiven(): void
    {
        self::assertSame(
            ['source' => 'id', 'output' => 'x'],
            (new PgSqlReturningProjectionParser())->parseItem('id AS x'),
        );
    }

    public function testAsIndexAnswersWhereTheAsIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('id AS x', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(1, (new PgSqlReturningProjectionParser())->asIndex($tokens));
    }

    public function testAsIndexIsNothingWhereNoNameIsGiven(): void
    {
        $tokens = SqlTokenStream::tokenize('id', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlReturningProjectionParser())->asIndex($tokens));
    }

    public function testIsIdentifierPathReportsTokensSpellingAName(): void
    {
        $tokens = SqlTokenStream::tokenize('t.id', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlReturningProjectionParser())->isIdentifierPath($tokens));
    }

    public function testIsIdentifierPathIsFalseForAnExpression(): void
    {
        $tokens = SqlTokenStream::tokenize('id + 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlReturningProjectionParser())->isIdentifierPath($tokens));
    }

    public function testIdentifierNameTakesTheQuotingOffAName(): void
    {
        $tokens = SqlTokenStream::tokenize('"order"', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('order', (new PgSqlReturningProjectionParser())->identifierName($tokens[0]));
    }

    public function testIdentifierNameIsNothingForATokenThatIsNotAName(): void
    {
        $tokens = SqlTokenStream::tokenize('1', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlReturningProjectionParser())->identifierName($tokens[0]));
    }

}
