<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(RoutineComment::class)]
#[Medium]
final class RoutineCommentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerRenderWritesTheCommentText(): iterable
    {
        yield 'MySQL 5.6 plain text' => ['mysql-5.6.51', "alter procedure p comment 'adds'", "ALTER PROCEDURE p COMMENT 'adds'", 'adds'];
        yield 'MySQL 8.0 doubled quote' => ['mysql-8.0.44', "alter function f comment 'it''s'", "ALTER FUNCTION f COMMENT 'it''s'", "it's"];
        yield 'MySQL 9.1 double-quoted text' => ['mysql-9.1.0', 'alter function f comment "two words"', "ALTER FUNCTION f COMMENT 'two words'", 'two words'];
    }

    #[DataProvider('providerRenderWritesTheCommentText')]
    public function testRenderWritesTheCommentText(string $release, string $sql, string $expected, string $text): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(RoutineComment::class, $characteristic);

        self::assertSame($text, $characteristic->text->value);
        self::assertSame($expected, $alter->toString());
    }

    public function testRenderWritesAConstructedCharacteristic(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new RoutineComment(new Text('adds')))->render($out);

        self::assertSame("COMMENT 'adds'", (new Lexical())->join($out->pieces()));
    }

    public function testAHexadecimalTextIsRejected(): void
    {
        $this->expectExceptionMessage('A comment is written as a quoted string.');

        new RoutineComment(new Text('41', EscapeRule::Backslash, Radix::Hexadecimal));
    }

    public function testABitTextIsRejected(): void
    {
        $this->expectExceptionMessage('A comment is written as a quoted string.');

        new RoutineComment(new Text('01', EscapeRule::Backslash, Radix::Bit));
    }
}
