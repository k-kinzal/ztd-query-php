<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\DefaultExpression as Subject;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\SqlText::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testExtractDefaultInterpretsLiteralKinds(string $declaration, int|float|bool|string|null $expected): void
    {
        $sql = "CREATE TABLE t (c {$declaration})";
        $tree = (new SqliteParser())->parse($sql);

        self::assertSame($expected, (new Subject())->extractDefault($tree->find('ccons')[0]));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaults(): array
    {
        return [
            ['INT DEFAULT 12 NOT NULL', 12],
            ['INT DEFAULT -12 CHECK (c > 0)', -12],
            ['INT DEFAULT +3', 3],
            ['INT DEFAULT 1', 1],
            ['INT DEFAULT 0', 0],
            ['NUMERIC DEFAULT 12.5', 12.5],
            ['NUMERIC DEFAULT -12.5', -12.5],
            ['NUMERIC DEFAULT 1e3', 1000.0],
            ["TEXT DEFAULT 'ready'", 'ready'],
            ["TEXT DEFAULT 'it''s'", "it's"],
            ["TEXT DEFAULT 'a::b' UNIQUE", 'a::b'],
            ["TEXT DEFAULT ''", ''],
            ['TEXT DEFAULT "dq"', 'dq'],
            ['BOOLEAN DEFAULT TRUE', true],
            ['BOOLEAN DEFAULT tRuE NOT NULL', true],
            ['BOOLEAN DEFAULT false', false],
            ['TEXT DEFAULT NULL', null],
            ['TEXT DEFAULT nUlL', null],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['TEXT DEFAULT CURRENT_DATE', 'CURRENT_DATE'],
            ['INT DEFAULT (1 + 2)', '(1 + 2)'],
            ['INT DEFAULT (abs(-1))', '(abs(-1))'],
            ["BLOB DEFAULT x'00'", "x'00'"],
            ['TEXT DEFAULT something', 'something'],
        ];
    }

    public function testExtractDefaultReturnsNullWithoutAValue(): void
    {
        self::assertNull((new Subject())->extractDefault(new Node('ccons', 0, [new \SqlParser\Lexer\Token(1, 'DEFAULT', 'DEFAULT', 0)])));
    }

    public function testIdentifierValueReadsBooleansAndQuotedWords(): void
    {
        self::assertTrue((new Subject())->identifierValue('true'));
        self::assertFalse((new Subject())->identifierValue('FALSE'));
        self::assertSame('a"b', (new Subject())->identifierValue('"a""b"'));
        self::assertSame('CURRENT_TIMESTAMP', (new Subject())->identifierValue('CURRENT_TIMESTAMP'));
    }
}
