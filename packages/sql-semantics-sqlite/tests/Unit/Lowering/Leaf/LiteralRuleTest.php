<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\LiteralRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TimeKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(LiteralRule::class)]
#[Medium]
final class LiteralRuleTest extends TestCase
{
    public function testTermLowersNullAndADecodedString(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT NULL, 'it''s'");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        self::assertInstanceOf(NullLiteral::class, $operation->statement->columns[0]->expression);
        self::assertInstanceOf(TextLiteral::class, $operation->statement->columns[1]->expression);
        self::assertSame("it's", $operation->statement->columns[1]->expression->value);
        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
        self::assertInstanceOf(Known::class, $operation->field(1)->type);
        self::assertSame(Storage::Text, $operation->field(1)->type->descriptor);
        self::assertSame("SELECT NULL, 'it''s'", $operation->toString());
    }

    public function testTermLowersABlobWithUpperCasedDigits(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT x'0aff', X'AB'");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(BlobLiteral::class, $operation->statement->columns[0]->expression);
        self::assertSame('0AFF', $operation->statement->columns[0]->expression->hex);
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Blob, $operation->field(0)->type->descriptor);
        self::assertSame("SELECT x'0AFF', x'AB'", $operation->toString());
    }

    public function testTermLowersTheCurrentTimeKeywords(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT current_time, CURRENT_DATE, Current_Timestamp');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([TimeKeyword::Time, TimeKeyword::Date, TimeKeyword::Timestamp], array_map(static function (object $column): TimeKeyword {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(CurrentTime::class, $column->expression);

            return $column->expression->keyword;
        }, $operation->statement->columns));
        self::assertSame('SELECT CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP', $operation->toString());
    }

    public function testTermLowersAnIntegerAndDropsItsDigitSeparators(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 42, 1_000_000');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['42', '1000000'], array_map(static function (object $column): string {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(IntegerLiteral::class, $column->expression);

            return $column->expression->digits;
        }, $operation->statement->columns));
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Integer, $operation->field(0)->type->descriptor);
        self::assertSame('SELECT 42, 1000000', $operation->toString());
    }

    public function testPlainLowersAFloatingPointTokenByItsParts(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1.5, 2.');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([['1', '5', null], ['2', '', null]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(RealLiteral::class, $column->expression);

            return [$column->expression->whole, $column->expression->fraction, $column->expression->exponent];
        }, $operation->statement->columns));
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Real, $operation->field(0)->type->descriptor);
        self::assertSame('SELECT 1.5, 2.', $operation->toString());
    }

    public function testNumberLowersAHexadecimalIntegerWithUpperCasedDigits(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 0x1f, 0XaB_cd');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['1F', 'ABCD'], array_map(static function (object $column): string {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(HexLiteral::class, $column->expression);

            return $column->expression->digits;
        }, $operation->statement->columns));
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Integer, $operation->field(0)->type->descriptor);
        self::assertSame('SELECT 0x1F, 0xABCD', $operation->toString());
    }

    public function testNumberLowersEverySpellingOfARealNumber(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT .5, 1e5, 1E+5, 1.5e-3, .5e2, 1.e3');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([['', '5', null], ['1', null, '5'], ['1', null, '+5'], ['1', '5', '-3'], ['', '5', '2'], ['1', '', '3']], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(RealLiteral::class, $column->expression);

            return [$column->expression->whole, $column->expression->fraction, $column->expression->exponent];
        }, $operation->statement->columns));
        self::assertSame('SELECT .5, 1e5, 1e+5, 1.5e-3, .5e2, 1.e3', $operation->toString());
    }

    public function testNumberDropsTheDigitSeparatorsOfARealToken(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1_000.5, 1_0e1_0');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([['1000', '5', null], ['10', null, '10']], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(RealLiteral::class, $column->expression);

            return [$column->expression->whole, $column->expression->fraction, $column->expression->exponent];
        }, $operation->statement->columns));
        self::assertSame('SELECT 1000.5, 10e10', $operation->toString());
    }
}
