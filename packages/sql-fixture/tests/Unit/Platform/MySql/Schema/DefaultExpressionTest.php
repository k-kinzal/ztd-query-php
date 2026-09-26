<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\DefaultExpression as Subject;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\SqlText::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testExtractDefaultInterpretsLiteralKinds(string $declaration, int|float|bool|string|null $expected): void
    {
        $sql = "CREATE TABLE t (c {$declaration})";
        $tree = (new MySqlParser())->parse($sql);

        self::assertSame($expected, (new Subject())->extractDefault($tree->find('column_attribute')[0]));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaults(): array
    {
        return [
            ['INT DEFAULT 42', 42],
            ['INT DEFAULT -42', -42],
            ['INT DEFAULT +7', 7],
            ['DECIMAL(5,2) DEFAULT 9.99', 9.99],
            ['DECIMAL(5,2) DEFAULT -9.99', -9.99],
            ['FLOAT DEFAULT 1e3', 1000.0],
            ['BIGINT DEFAULT 9223372036854775807', PHP_INT_MAX],
            ["VARCHAR(5) DEFAULT 'hello'", 'hello'],
            ["VARCHAR(5) DEFAULT 'it''s'", "it's"],
            ['VARCHAR(5) DEFAULT "dq"', 'dq'],
            ["VARCHAR(5) DEFAULT N'nat'", 'nat'],
            ["VARCHAR(5) DEFAULT _utf8mb4'intro'", 'intro'],
            ['VARCHAR(5) DEFAULT NULL', null],
            ['VARCHAR(5) DEFAULT null', null],
            ['BOOLEAN DEFAULT TRUE', true],
            ['BOOLEAN DEFAULT true', true],
            ['BOOLEAN DEFAULT FALSE', false],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP(6)', 'CURRENT_TIMESTAMP(6)'],
            ['TIMESTAMP DEFAULT NOW()', 'NOW()'],
            ["BIT(1) DEFAULT b'0'", "b'0'"],
            ['INT DEFAULT 0x1F', '0x1F'],
            ["DATE DEFAULT DATE '2020-01-01'", "DATE '2020-01-01'"],
            ['CHAR(36) DEFAULT (uuid())', '(uuid())'],
            ['INT DEFAULT (1 + 2)', '(1 + 2)'],
        ];
    }

    public function testExtractDefaultSkipsAttributesBeforeTheDefault(): void
    {
        $sql = "CREATE TABLE t (c VARCHAR(5) NOT NULL DEFAULT 'x' COMMENT 'c')";
        $tree = (new MySqlParser())->parse($sql);

        self::assertSame('x', (new Subject())->extractDefault($tree->find('column_attribute')[1]));
    }

    public function testStringsJoinsAdjacentStringsAndLeavesOtherLiteralsAlone(): void
    {
        $sql = "CREATE TABLE t (a VARCHAR(4) DEFAULT 'x' 'y', b DATE DEFAULT DATE '2020-01-01', c BIT(1) DEFAULT b'0')";
        $literals = (new MySqlParser())->parse($sql)->find('now_or_signed_literal');

        self::assertSame('xy', (new Subject())->strings($literals[0]));
        self::assertNull((new Subject())->strings($literals[1]));
        self::assertNull((new Subject())->strings($literals[2]));
    }
}
