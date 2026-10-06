<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\TypeNameRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;

#[CoversClass(TypeNameRule::class)]
#[Medium]
final class TypeNameRuleTest extends TestCase
{
    public function testNamedIsNullWhenNoTypeIsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $cast = $semantics->analyze('SELECT CAST(a AS) FROM t')->statement;
        $table = $semantics->analyze('CREATE TABLE t (a)')->statement;

        self::assertInstanceOf(Select::class, $cast);
        self::assertInstanceOf(ResultColumn::class, $cast->columns[0]);
        self::assertInstanceOf(Cast::class, $cast->columns[0]->expression);
        self::assertNull($cast->columns[0]->expression->target);
        self::assertInstanceOf(CreateTable::class, $table);
        self::assertNull($table->columns[0]->type);
    }

    public function testNamedKeepsTheWordsOfATypeNameInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a UNSIGNED BIG INT, b Text)');

        self::assertInstanceOf(CreateTable::class, $operation->statement);
        self::assertSame(['UNSIGNED', 'BIG', 'INT'], array_map(static fn (Word $word): string => $word->name->value, $operation->statement->columns[0]->type->words ?? []));
        self::assertSame(['Text'], array_map(static fn (Word $word): string => $word->name->value, $operation->statement->columns[1]->type->words ?? []));
        self::assertSame([], $operation->statement->columns[0]->type->arguments ?? null);
        self::assertSame('CREATE TABLE t (a UNSIGNED BIG INT, b Text)', $operation->toString());
    }

    public function testNamedKeepsOneOrTwoNumericArgumentsWithTheirSigns(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(a AS VARCHAR(10)) AS c1, CAST(a AS DECIMAL(+10, -2)) AS c2 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[[null, '10']], [[NumberSign::Plus, '10'], [NumberSign::Minus, '2']]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Cast::class, $column->expression);

            return array_map(static function (SignedNumber $argument): array {
                self::assertInstanceOf(IntegerLiteral::class, $argument->number);

                return [$argument->sign, $argument->number->digits];
            }, $column->expression->target->arguments ?? []);
        }, $operation->statement->columns));
        self::assertSame('SELECT CAST(a AS VARCHAR(10)) AS c1, CAST(a AS DECIMAL(+10,-2)) AS c2 FROM t', $operation->toString());
    }

    public function testWordsKeepTheQuotingOfEachWord(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(a AS "my" [type] `t` \'q\' plain) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(Cast::class, $operation->statement->columns[0]->expression);
        $words = $operation->statement->columns[0]->expression->target->words ?? [];
        self::assertSame(['my', 'type', 't', 'q', 'plain'], array_map(static fn (Word $word): string => $word->name->value, $words));
        self::assertSame([WordQuote::Double, WordQuote::Bracket, WordQuote::Backtick, WordQuote::Single, WordQuote::Bare], array_map(static fn (Word $word): WordQuote => $word->quote, $words));
        self::assertSame('SELECT CAST(a AS "my" [type] `t` \'q\' plain) FROM t', $operation->toString());
    }

    public function testSignedLowersARealAndAHexadecimalArgument(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(a AS N(1.5, -0x10)) AS c1 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(Cast::class, $operation->statement->columns[0]->expression);
        $arguments = $operation->statement->columns[0]->expression->target->arguments ?? [];
        self::assertCount(2, $arguments);
        self::assertNull($arguments[0]->sign);
        self::assertInstanceOf(RealLiteral::class, $arguments[0]->number);
        self::assertSame('5', $arguments[0]->number->fraction);
        self::assertSame(NumberSign::Minus, $arguments[1]->sign);
        self::assertInstanceOf(HexLiteral::class, $arguments[1]->number);
        self::assertSame('10', $arguments[1]->number->digits);
        self::assertSame('SELECT CAST(a AS N(1.5,-0x10)) AS c1 FROM t', $operation->toString());
    }

    public function testPlusNumberLowersAnUnsignedAndAPlusSignedNumber(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(a AS N(3, +.5)) AS c1 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(Cast::class, $operation->statement->columns[0]->expression);
        $arguments = $operation->statement->columns[0]->expression->target->arguments ?? [];
        self::assertCount(2, $arguments);
        self::assertNull($arguments[0]->sign);
        self::assertSame(NumberSign::Plus, $arguments[1]->sign);
        self::assertInstanceOf(RealLiteral::class, $arguments[1]->number);
        self::assertSame('', $arguments[1]->number->whole);
        self::assertSame('SELECT CAST(a AS N(3,+.5)) AS c1 FROM t', $operation->toString());
    }

    public function testMinusNumberLowersAMinusSignedNumber(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(a AS N(-1e2)) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(Cast::class, $operation->statement->columns[0]->expression);
        $arguments = $operation->statement->columns[0]->expression->target->arguments ?? [];
        self::assertCount(1, $arguments);
        self::assertSame(NumberSign::Minus, $arguments[0]->sign);
        self::assertInstanceOf(RealLiteral::class, $arguments[0]->number);
        self::assertSame('2', $arguments[0]->number->exponent);
        self::assertSame('SELECT CAST(a AS N(-1e2)) FROM t', $operation->toString());
    }
}
