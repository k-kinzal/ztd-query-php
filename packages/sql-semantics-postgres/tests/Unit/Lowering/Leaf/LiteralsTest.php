<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Literals;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NamedParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Statement\Scalar;

#[CoversClass(Literals::class)]
#[Medium]
final class LiteralsTest extends TestCase
{
    public function testStringTokenDecodesAnyStringSpelling(): void
    {
        self::assertSame("a'b", (new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172))->literals->stringToken(new Token(1, 'SCONST', "E'a\\'b'", 0))->value);
    }

    public function testStringLowersAStringConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT $$a$$');
        self::assertSame('a', $lowering->literals->string($tree->find('Sconst')[0])->value);
    }

    public function testNumberTokenKeepsTheWrittenTextOfAnFconst(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertSame('0xFFFFFFFFFF', $lowering->literals->numberToken(new Token(1, 'FCONST', '0xFFFFFFFFFF', 0))->text);
        self::assertSame('1.50E-03', $lowering->literals->numberToken(new Token(1, 'FCONST', '1.50E-03', 0))->text);
    }

    public function testNumberTokenKeepsTheTextASettingReceives(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        self::assertSame(
            ['SET application_name TO 1e2', 'SET application_name TO .5', 'SET application_name TO - .5', 'SET application_name TO 0001.50', 'SET application_name TO 0x1FFFFFFFFF'],
            [$semantics->analyze('SET application_name = 1e2')->toString(), $semantics->analyze('SET application_name = .5')->toString(), $semantics->analyze('SET application_name = -.5')->toString(), $semantics->analyze('SET application_name = 0001.50')->toString(), $semantics->analyze('SET application_name = 0x1FFFFFFFFF')->toString()],
        );
    }

    public function testIntegerLowersAnIntegerConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 0x10');
        self::assertSame('16', $lowering->literals->integer($tree->find('Iconst')[0])->digits);
    }

    public function testNumberLowersAnIntegerOrNumericConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FETCH FIRST + 2.5 ROWS ONLY');
        self::assertInstanceOf(NumericConstant::class, $lowering->literals->number($tree->find('I_or_F_const')[0]));
    }

    public function testSignedKeepsTheMinusSignAndDropsThePlusSign(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s START - 5 INCREMENT + 1.5');
        $numbers = $tree->find('NumericOnly');
        self::assertTrue($lowering->literals->signed($numbers[0])->negative);
        self::assertFalse($lowering->literals->signed($numbers[1])->negative);
    }

    public function testSignedListLowersEveryNumber(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('GRANT ALL ON LARGE OBJECT 1, -2 TO x');
        self::assertCount(2, $lowering->literals->signedList($tree->find('NumericOnly_list')[0]));
    }

    public function testParameterCanonicalizesTheNumber(): void
    {
        $parameter = (new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172))->literals->parameter(new Token(1, 'PARAM', '$007', 0));
        self::assertInstanceOf(PositionalParameter::class, $parameter);
        self::assertSame('7', $parameter->number);
    }

    public function testParameterLowersANamedPlaceholder(): void
    {
        $parameter = (new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172))->literals->parameter(new Token(1, 'PARAM', ':id', 0));
        self::assertInstanceOf(NamedParameter::class, $parameter);
        self::assertSame('id', $parameter->name);
    }

    public function testParameterRejectsANumberThePostgreSql17ScannerRejects(): void
    {
        $this->expectException(AnalysisException::class);
        (new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172))->literals->parameter(new Token(1, 'PARAM', '$2147483648', 0));
    }

    public function testParameterKeepsALargeNumberInPostgreSql16(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql166)), new Leaves(), GrammarRelease::PostgreSql166);
        $parameter = $lowering->literals->parameter(new Token(1, 'PARAM', '$2147483648', 0));
        self::assertInstanceOf(PositionalParameter::class, $parameter);
        self::assertSame('2147483648', $parameter->number);
    }

    public function testConstantLowersEveryPlainConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT 1, 1.5, 'a', B'10', X'1F', TRUE, FALSE, NULL");
        $constants = array_map(static fn (\SqlParser\Parser\Node $node): Scalar => $lowering->literals->constant($node), $tree->find('AexprConst'));
        self::assertSame([Constant::class, Constant::class, Constant::class, Constant::class, Constant::class, BooleanLiteral::class, BooleanLiteral::class, NullLiteral::class], array_map(static fn (Scalar $constant): string => $constant::class, $constants));
    }

    public function testConstantLowersTypedConstants(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT date '2024-01-01', interval '1' day, interval(2) '1', N'x'");
        $constants = $tree->find('AexprConst');
        $date = $lowering->literals->constant($constants[0]);
        $interval = $lowering->literals->constant($constants[1]);
        self::assertInstanceOf(TypedLiteral::class, $date);
        self::assertSame('2024-01-01', $date->value->value);
        self::assertInstanceOf(TypedLiteral::class, $interval);
        self::assertSame('1', $interval->value->value);
        self::assertInstanceOf(TypedLiteral::class, $lowering->literals->constant($constants[2]));
        self::assertInstanceOf(TypedLiteral::class, $lowering->literals->constant($constants[3]));
    }
}
