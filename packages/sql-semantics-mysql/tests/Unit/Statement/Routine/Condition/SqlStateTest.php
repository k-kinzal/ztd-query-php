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
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(SqlState::class)]
#[Medium]
final class SqlStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerValidTellsTheFormOfTheValue(): iterable
    {
        yield 'user-defined exception' => ['45000', true];
        yield 'warning' => ['01000', true];
        yield 'letters and digits' => ['42S02', true];
        yield 'not found' => ['02000', true];
        yield 'success' => ['00000', false];
        yield 'success class' => ['00123', false];
        yield 'four characters' => ['4500', false];
        yield 'six characters' => ['450000', false];
        yield 'lower-case letter' => ['4500a', false];
        yield 'punctuation' => ['45-00', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('providerValidTellsTheFormOfTheValue')]
    public function testValidTellsTheFormOfTheValue(string $value, bool $expected): void
    {
        self::assertSame($expected, (new SqlState(new Text($value)))->valid());
    }

    public function testRenderWritesTheKeywordAndTheQuotedValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new SqlState(new Text('45000')))->render($out);

        self::assertSame("SQLSTATE '45000'", (new Lexical())->join($out->pieces()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRenderDropsTheWordValue(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.4' => ['mysql-8.4.7'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRenderDropsTheWordValue')]
    public function testRenderDropsTheWordValue(string $release): void
    {
        $signal = (new Semantics(Dialect::MySql, $release))->analyze("signal sqlstate value '42S02'");
        $statement = $signal->statement;
        self::assertInstanceOf(Signal::class, $statement);
        self::assertInstanceOf(SqlState::class, $statement->condition);

        self::assertSame('42S02', $statement->condition->state->value);
        self::assertSame("SIGNAL SQLSTATE '42S02'", $signal->toString());
    }

    public function testRefusesAHexadecimalText(): void
    {
        $this->expectExceptionMessage('An SQLSTATE value is written as a quoted string.');

        new SqlState(new Text('4500', EscapeRule::Backslash, Radix::Hexadecimal));
    }

    public function testRefusesABitText(): void
    {
        $this->expectExceptionMessage('An SQLSTATE value is written as a quoted string.');

        new SqlState(new Text('0101', EscapeRule::Backslash, Radix::Bit));
    }
}
