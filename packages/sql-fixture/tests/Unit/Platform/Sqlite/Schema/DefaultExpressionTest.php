<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\Sqlite\Schema\ColumnConstraints;
use SqlFixture\Platform\Sqlite\Schema\DefaultExpression as Subject;
use Tests\Statement\SqliteStatements;

#[CoversClass(Subject::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnConstraints::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testEvaluateReadsLiteralsAndWords(string $declaration, int|float|bool|string|null $expected): void
    {
        $default = (new ColumnConstraints())->read(SqliteStatements::columns("c {$declaration}")[0]->constraints)->default;
        self::assertNotNull($default);

        self::assertSame($expected, (new Subject())->evaluate($default));
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
            ['INT DEFAULT 0', 0],
            ['INT DEFAULT 0x1F', 31],
            ['INT DEFAULT -0x10', -16],
            ['INT DEFAULT 0xffffffffffffffff', -1],
            ['INT DEFAULT -0002', -2],
            ['NUMERIC DEFAULT 12.5', 12.5],
            ['NUMERIC DEFAULT -12.5', -12.5],
            ['NUMERIC DEFAULT .5', 0.5],
            ['NUMERIC DEFAULT 1e3', 1000.0],
            ["TEXT DEFAULT 'ready'", 'ready'],
            ["TEXT DEFAULT 'it''s'", "it's"],
            ["TEXT DEFAULT 'a::b' UNIQUE", 'a::b'],
            ["TEXT DEFAULT ''", ''],
            ['BOOLEAN DEFAULT TRUE', true],
            ['BOOLEAN DEFAULT tRuE NOT NULL', true],
            ['BOOLEAN DEFAULT false', false],
            ['TEXT DEFAULT NULL', null],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP', null],
            ["BLOB DEFAULT x'0041'", "\0A"],
            ['TEXT DEFAULT something', 'something'],
            ['TEXT DEFAULT "true"', 'true'],
            ['TEXT DEFAULT [false]', 'false'],
        ];
    }

    public function testHexadecimalReadsDigitsAsA64BitTwosComplementInteger(): void
    {
        self::assertSame(31, (new Subject())->hexadecimal('1F'));
        self::assertSame(-1, (new Subject())->hexadecimal('ffffffffffffffff'));
        self::assertSame(PHP_INT_MIN, (new Subject())->hexadecimal('8000000000000000'));
    }
}
