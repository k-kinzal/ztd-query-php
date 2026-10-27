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
     * @return iterable<string, array{string}>
     */
    public static function providerValidTellsTheFormOfTheValue(): iterable
    {
        yield 'user-defined exception' => ['45000'];
        yield 'warning' => ['01000'];
        yield 'letters and digits' => ['42S02'];
        yield 'not found' => ['02000'];
    }

    #[DataProvider('providerValidTellsTheFormOfTheValue')]
    public function testValidTellsTheFormOfTheValue(string $value): void
    {
        self::assertTrue(SqlState::accepts($value));
        self::assertTrue((new SqlState(new Text($value)))->valid());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidValues(): iterable
    {
        yield 'success' => ['00000'];
        yield 'success class' => ['00123'];
        yield 'four characters' => ['4500'];
        yield 'six characters' => ['450000'];
        yield 'lower-case letter' => ['4500a'];
        yield 'punctuation' => ['45-00'];
        yield 'empty' => [''];
        yield 'multibyte' => ['45猫00'];
        yield 'embedded nul' => ["45\0AA"];
    }

    #[DataProvider('providerInvalidValues')]
    public function testRefusesValuesOutsideTheSqlStateDomain(string $value): void
    {
        self::assertFalse(SqlState::accepts($value));
        $this->expectExceptionMessage('An SQLSTATE value has five uppercase letters or digits and excludes class 00.');

        new SqlState(new Text($value));
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
        $statement = $signal;
        self::assertInstanceOf(Signal::class, $statement);
        self::assertInstanceOf(SqlState::class, $statement->condition);

        self::assertSame('42S02', $statement->condition->state->value);
        self::assertSame("SIGNAL SQLSTATE '42S02'", $signal->toString());
    }

    public function testRefusesAHexadecimalText(): void
    {
        $this->expectExceptionMessage('An SQLSTATE value is written as a quoted string.');

        new SqlState(new Text('4500', Radix::Hexadecimal));
    }

    public function testRefusesABitText(): void
    {
        $this->expectExceptionMessage('An SQLSTATE value is written as a quoted string.');

        new SqlState(new Text('0101', Radix::Bit));
    }
    public function testAcceptsNamesTheSqlStateOperandDomain(): void
    {
        self::assertTrue(SqlState::accepts('42S02'));
        self::assertFalse(SqlState::accepts('00ABC'));
    }
}
