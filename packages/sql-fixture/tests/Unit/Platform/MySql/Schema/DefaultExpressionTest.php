<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\MySql\Schema\ColumnAttributes;
use SqlFixture\Platform\MySql\Schema\DefaultExpression as Subject;
use SqlFixture\Platform\MySql\Schema\TypeParameters;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use Tests\Statement\MySqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnAttributes::class)]
#[UsesClass(TypeParameters::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testEvaluateReadsTheValueOfALiteral(string $declaration, int|float|bool|string|null $expected): void
    {
        $column = MySqlStatements::columns("c {$declaration}")[0];
        $default = (new ColumnAttributes())->read($column->specification->columnAttributes())->default;
        self::assertNotNull($default);

        self::assertSame($expected, (new Subject())->evaluate($default, (new TypeParameters())->numeric($column->specification->dataType())));
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
            ["VARCHAR(5) DEFAULT 'a\\'b'", "a'b"],
            ["VARCHAR(5) DEFAULT 'x' 'y'", 'xy'],
            ['VARCHAR(5) DEFAULT "dq"', 'dq'],
            ["VARCHAR(5) DEFAULT N'nat'", 'nat'],
            ["VARCHAR(5) DEFAULT _utf8mb4'intro'", 'intro'],
            ['VARCHAR(5) DEFAULT NULL', null],
            ['BOOLEAN DEFAULT TRUE', true],
            ['BOOLEAN DEFAULT false', false],
            ["BIT(1) DEFAULT b'1'", 1],
            ['INT DEFAULT 0x1F', 31],
            ['INT DEFAULT -0002', -2],
            ['BIGINT UNSIGNED DEFAULT 0xFFFFFFFFFFFFFFFF', 18446744073709551615.0],
            ["VARBINARY(2) DEFAULT X'4142'", 'AB'],
            ["DATE DEFAULT DATE '2020-01-01'", '2020-01-01'],
        ];
    }

    public function testEvaluateLeavesExpressionsWithoutAValue(): void
    {
        $column = MySqlStatements::columns('c TIMESTAMP DEFAULT CURRENT_TIMESTAMP')[0];
        $default = (new ColumnAttributes())->read($column->specification->columnAttributes())->default;

        self::assertNotNull($default);
        self::assertNull((new Subject())->evaluate($default, false));
    }

    public function testEvaluateReadsARadixLiteralAsBytesOutsideANumericColumn(): void
    {
        self::assertSame('A', (new Subject())->evaluate(new RadixLiteral(Radix::Hexadecimal, '41'), false));
        self::assertSame(65, (new Subject())->evaluate(new RadixLiteral(Radix::Hexadecimal, '41'), true));
        self::assertSame(5, (new Subject())->evaluate(new RadixLiteral(Radix::Bit, '101'), true));
    }
}
