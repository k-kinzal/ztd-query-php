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
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ExternalBody::class)]
#[Medium]
final class ExternalBodyTest extends TestCase
{
    public function testRenderKeepsAQuotedCode(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS 'return zz'");
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);

        self::assertEquals(new ExternalBody(new Text('return zz')), $statement->body);
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesAsAndTheCode(): iterable
    {
        yield 'MySQL 8.4 quoted' => ['mysql-8.4.7', "create procedure p() language sql as 'x'", "CREATE PROCEDURE p() LANGUAGE SQL AS 'x'"];
        yield 'MySQL 9.0 dollar-quoted' => [
            'mysql-9.0.1',
            'create function f() returns int language javascript as $js$ return 1 $js$',
            'CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS $js$ return 1 $js$',
        ];
        yield 'MySQL 9.1 quoted with parameters' => [
            'mysql-9.1.0',
            "create procedure p(in a int, out b varchar(3) collate utf8mb4_bin, inout c bigint) language javascript as 'x'",
            "CREATE PROCEDURE p(IN a INT, OUT b VARCHAR(3) COLLATE utf8mb4_bin, INOUT c BIGINT) LANGUAGE javascript AS 'x'",
        ];
        yield 'MySQL 9.1 function' => ['mysql-9.1.0', "create function f() returns int language javascript as 'return 1'", "CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'"];
    }

    #[DataProvider('providerRenderWritesAsAndTheCode')]
    public function testRenderWritesAsAndTheCode(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedBody(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $quoted = new Output(new Codec($semantics->profile()->grammar));
        (new ExternalBody(new Text("it's")))->render($quoted);
        $dollar = new Output(new Codec($semantics->profile()->grammar));
        (new ExternalBody(new DollarQuotedText('return 1', 'js')))->render($dollar);

        self::assertSame("AS 'it''s'", (new Lexical())->join($quoted->pieces()));
        self::assertSame('AS $js$return 1$js$', (new Lexical())->join($dollar->pieces()));
    }

    public function testAHexadecimalCodeIsRejected(): void
    {
        $this->expectExceptionMessage('The code of an external body is written as a quoted string.');

        new ExternalBody(new Text('41', EscapeRule::Backslash, Radix::Hexadecimal));
    }
}
