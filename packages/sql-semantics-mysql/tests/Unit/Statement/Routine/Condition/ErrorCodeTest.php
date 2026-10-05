<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(ErrorCode::class)]
#[Medium]
final class ErrorCodeTest extends TestCase
{
    public function testRenderWritesTheCode(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ErrorCode(new Numeral('1051')))->render($out);

        self::assertSame('1051', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheCodesOfADeclarationAndAHandler(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR c, 1052 BEGIN END; END');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $declaration = $body->declarations[0];
        self::assertInstanceOf(ConditionDeclaration::class, $declaration);
        self::assertInstanceOf(ErrorCode::class, $declaration->value);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        self::assertInstanceOf(ConditionName::class, $handler->conditions[0]);
        self::assertInstanceOf(ErrorCode::class, $handler->conditions[1]);

        self::assertSame('1051', $declaration->value->code->text);
        self::assertSame('1052', $handler->conditions[1]->code->text);
        self::assertSame([], $create->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR c, 1052 BEGIN END; END', $create->toString());
    }

    #[DataProvider('providerValidTellsWhetherTheCodeIsNotZero')]
    public function testValidTellsWhetherTheCodeIsNotZero(string $text, bool $hexadecimal, bool $expected): void
    {
        self::assertSame($expected, (new ErrorCode(new Numeral($text, $hexadecimal)))->valid());
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerValidTellsWhetherTheCodeIsNotZero(): iterable
    {
        yield 'a code' => ['1051', false, true];
        yield 'zero' => ['0', false, false];
        yield 'zeros' => ['000', false, false];
        yield 'a decimal below one' => ['0.9', false, false];
        yield 'a decimal without integer part' => ['.5', false, false];
        yield 'a floating number' => ['1e2', false, true];
        yield 'a hexadecimal code' => ['0A', true, true];
        yield 'a hexadecimal zero' => ['00', true, false];
        yield 'an empty hexadecimal literal' => ['', true, false];
    }

    #[DataProvider('providerNumberAnswersTheErrorNumberTheServerKeeps')]
    public function testNumberAnswersTheErrorNumberTheServerKeeps(string $text, bool $hexadecimal, int $expected): void
    {
        self::assertSame($expected, (new ErrorCode(new Numeral($text, $hexadecimal)))->number());
    }

    /**
     * @return iterable<string, array{string, bool, int}>
     */
    public static function providerNumberAnswersTheErrorNumberTheServerKeeps(): iterable
    {
        yield 'a code' => ['1051', false, 1051];
        yield 'leading zeros' => ['001051', false, 1051];
        yield 'a floating number' => ['1e2', false, 1];
        yield 'a decimal' => ['1051.9', false, 1051];
        yield 'a hexadecimal literal' => ['41b', true, 1051];
        yield 'beyond 32 bits' => ['4294967297', false, 1];
        yield 'beyond 64 bits' => ['99999999999999999999999', false, 4294967295];
        yield 'the largest 64-bit value' => ['18446744073709551615', false, 4294967295];
        yield 'a hexadecimal literal beyond 32 bits' => ['100000001', true, 1];
        yield 'a hexadecimal literal beyond the signed 64-bit range' => ['8000000000000001', true, 4294967295];
    }

    #[DataProvider('providerDigitsAnswersTheDigitsTheGrammarConverts')]
    public function testDigitsAnswersTheDigitsTheGrammarConverts(string $text, bool $hexadecimal, string $expected): void
    {
        self::assertSame($expected, (new ErrorCode(new Numeral($text, $hexadecimal)))->digits());
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function providerDigitsAnswersTheDigitsTheGrammarConverts(): iterable
    {
        yield 'an integer' => ['1051', false, '1051'];
        yield 'a floating number' => ['12e3', false, '12'];
        yield 'a decimal' => ['7.5', false, '7'];
        yield 'a decimal without integer part' => ['.5', false, ''];
        yield 'a hexadecimal literal' => ['0aF', true, '0aF'];
    }

    public function testValidLetsTheProgramReportTheCodeZero(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0.5; END');

        self::assertSame(["Incorrect CONDITION value: '0'"], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0.5; END', $create->toString());
    }
}
