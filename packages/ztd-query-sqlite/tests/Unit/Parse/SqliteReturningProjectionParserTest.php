<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\Dialect\SqliteLexerProfile;
use ZtdQuery\Platform\Sqlite\Parse\SqliteReturningProjectionParser;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteReturningProjectionParser::class)]
#[UsesClass(SqliteLexerProfile::class)]
final class SqliteReturningProjectionParserTest extends TestCase
{
    public function testParsesSqliteQualifiedQuotedAliasesAndWildcard(): void
    {
        $projection = (new SqliteReturningProjectionParser())->parse(
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

    public function testUnquotesEscapedSqliteIdentifiersAndStatementTerminator(): void
    {
        $projection = (new SqliteReturningProjectionParser())->parse(
            'INSERT INTO users VALUES (1) RETURNING "source""name" AS `output``name`;',
        );
        self::assertNotNull($projection);

        self::assertSame([['output`name' => 'value']], $projection->project([['source"name' => 'value']]));
    }

    public function testReturnsNullWithoutSqliteReturningClause(): void
    {
        self::assertNull((new SqliteReturningProjectionParser())->parse('INSERT INTO users VALUES (1)'));
    }

    #[DataProvider('providerInvalidSqliteReturningProjection')]
    public function testRejectsMalformedSqliteProjection(string $projection): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new SqliteReturningProjectionParser())->parse('INSERT INTO users VALUES (1) RETURNING ' . $projection);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerInvalidSqliteReturningProjection(): array
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
            'number token' => ['12'],
            'string token' => ["'id'"],
            'unterminated double quote' => ['"id'],
            'unterminated backtick' => ['`id'],
        ];
    }
    public function testParseItemReadsTheColumnAnEntryNames(): void
    {
        self::assertSame(
            ['source' => 'id', 'output' => null],
            (new SqliteReturningProjectionParser())->parseItem('id'),
        );
    }

    public function testParseItemReadsTheNameAnEntryIsGiven(): void
    {
        self::assertSame(
            ['source' => 'id', 'output' => 'x'],
            (new SqliteReturningProjectionParser())->parseItem('id AS x'),
        );
    }

    public function testAsIndexAnswersWhereTheAsIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('id AS x', SqliteLexerProfile::create())->significantTokens();

        self::assertSame(1, (new SqliteReturningProjectionParser())->asIndex($tokens));
    }

    public function testAsIndexIsNothingWhereNoNameIsGiven(): void
    {
        $tokens = SqlTokenStream::tokenize('id', SqliteLexerProfile::create())->significantTokens();

        self::assertNull((new SqliteReturningProjectionParser())->asIndex($tokens));
    }

    public function testIsIdentifierPathReportsTokensSpellingAName(): void
    {
        $tokens = SqlTokenStream::tokenize('t.id', SqliteLexerProfile::create())->significantTokens();

        self::assertTrue((new SqliteReturningProjectionParser())->isIdentifierPath($tokens));
    }

    public function testIsIdentifierPathIsFalseForAnExpression(): void
    {
        $tokens = SqlTokenStream::tokenize('id + 1', SqliteLexerProfile::create())->significantTokens();

        self::assertFalse((new SqliteReturningProjectionParser())->isIdentifierPath($tokens));
    }

    public function testIdentifierNameTakesTheQuotingOffAName(): void
    {
        $tokens = SqlTokenStream::tokenize('"order"', SqliteLexerProfile::create())->significantTokens();

        self::assertSame('order', (new SqliteReturningProjectionParser())->identifierName($tokens[0]));
    }

    public function testIdentifierNameIsNothingForATokenThatIsNotAName(): void
    {
        $tokens = SqlTokenStream::tokenize('1', SqliteLexerProfile::create())->significantTokens();

        self::assertNull((new SqliteReturningProjectionParser())->identifierName($tokens[0]));
    }
}
