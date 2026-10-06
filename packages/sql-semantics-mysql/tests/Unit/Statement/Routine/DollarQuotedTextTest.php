<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(DollarQuotedText::class)]
#[Medium]
final class DollarQuotedTextTest extends TestCase
{
    public function testRenderKeepsTheTextBetweenTheDelimiters(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() LANGUAGE JAVASCRIPT AS $a$ $$ x $a$');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(ExternalBody::class, $statement->body);

        self::assertEquals(new DollarQuotedText(' $$ x ', 'a'), $statement->body->code);
        self::assertSame('CREATE PROCEDURE p() LANGUAGE JAVASCRIPT AS $a$ $$ x $a$', $create->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDelimiters(): iterable
    {
        yield 'MySQL 8.4 tagged' => [
            'mysql-8.4.7',
            'create function f() returns int language javascript as $js$ return 1 $js$',
            'CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS $js$ return 1 $js$',
        ];
        yield 'MySQL 9.0 tagged' => [
            'mysql-9.0.1',
            'create function f() returns int language javascript as $js$ return 1 $js$',
            'CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS $js$ return 1 $js$',
        ];
        yield 'MySQL 9.1 without tag' => ['mysql-9.1.0', 'create function f(a int) returns int language javascript as $$x$$', 'CREATE FUNCTION f(a INT) RETURNS INT LANGUAGE javascript AS $$x$$'];
        yield 'MySQL 9.1 blank text' => ['mysql-9.1.0', 'create procedure p() language javascript as $$ $$', 'CREATE PROCEDURE p() LANGUAGE javascript AS $$ $$'];
    }

    #[DataProvider('providerRenderWritesTheDelimiters')]
    public function testRenderWritesTheDelimiters(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedText(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new DollarQuotedText("it's 'quoted'", 'q'))->render($out);

        self::assertSame("\$q\$it's 'quoted'\$q\$", (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesAnEmptyTag(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new DollarQuotedText('return 1'))->render($out);

        self::assertSame('$$return 1$$', (new Lexical())->join($out->pieces()));
    }

    public function testATagWithADollarSignIsRejected(): void
    {
        $this->expectExceptionMessage('The tag of a dollar-quoted string holds no dollar sign and no space.');

        new DollarQuotedText('x', 'a$b');
    }

    public function testATagWithASpaceIsRejected(): void
    {
        $this->expectExceptionMessage('The tag of a dollar-quoted string holds no dollar sign and no space.');

        new DollarQuotedText('x', 'a b');
    }

    public function testATextHoldingItsClosingDelimiterIsRejected(): void
    {
        $this->expectExceptionMessage('The text of a dollar-quoted string cannot hold its closing delimiter.');

        new DollarQuotedText('a $js$ b', 'js');
    }

    public function testAnUntaggedTextHoldingTwoDollarSignsIsRejected(): void
    {
        $this->expectExceptionMessage('The text of a dollar-quoted string cannot hold its closing delimiter.');

        new DollarQuotedText('a $$ b');
    }
}
