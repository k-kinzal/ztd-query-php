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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CreateLoadableFunction::class)]
#[Medium]
final class CreateLoadableFunctionTest extends TestCase
{
    public function testDeriveStatementChangesNoContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $create = $semantics->analyze("CREATE AGGREGATE FUNCTION median RETURNS REAL SONAME 'udf.so'", [$table]);

        self::assertSame([], $create->facts->diagnostics);
        self::assertSame([], $create->facts->declarations);
        self::assertNull($create->facts->output);
    }

    public function testDeriveStatementReadsTheRegistration(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE FUNCTION IF NOT EXISTS f RETURNS INT SONAME 'u.so'");
        $statement = $create->statement;
        self::assertInstanceOf(CreateLoadableFunction::class, $statement);

        self::assertSame('f', $statement->name->value);
        self::assertSame(LoadableResult::Integer, $statement->returns);
        self::assertSame('u.so', $statement->library->value);
        self::assertFalse($statement->aggregate);
        self::assertTrue($statement->ifNotExists);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheRegistration(): iterable
    {
        yield 'MySQL 5.6 aggregate' => ['mysql-5.6.51', "create aggregate function median returns real soname 'udf.so'", "CREATE AGGREGATE FUNCTION median RETURNS REAL SONAME 'udf.so'"];
        yield 'MySQL 5.7 string result' => ['mysql-5.7.44', "create function f returns string soname 'u.so'", "CREATE FUNCTION f RETURNS STRING SONAME 'u.so'"];
        yield 'MySQL 8.0 IF NOT EXISTS' => ['mysql-8.0.44', "create function if not exists f returns integer soname 'u.so'", "CREATE FUNCTION IF NOT EXISTS f RETURNS INTEGER SONAME 'u.so'"];
        yield 'MySQL 9.1 INT keyword' => ['mysql-9.1.0', "create function f returns int soname 'u.so'", "CREATE FUNCTION f RETURNS INTEGER SONAME 'u.so'"];
        yield 'MySQL 9.1 aggregate IF NOT EXISTS' => [
            'mysql-9.1.0',
            'create aggregate function if not exists f returns decimal soname "u.so"',
            "CREATE AGGREGATE FUNCTION IF NOT EXISTS f RETURNS DECIMAL SONAME 'u.so'",
        ];
    }

    #[DataProvider('providerRenderWritesTheRegistration')]
    public function testRenderWritesTheRegistration(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedRegistration(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new CreateLoadableFunction(new Name('f'), LoadableResult::String, new Text('u.so')))->render($out);

        self::assertSame("CREATE FUNCTION f RETURNS STRING SONAME 'u.so'", (new Lexical())->join($out->pieces()));
    }

    public function testAHexadecimalLibraryIsRejected(): void
    {
        $this->expectExceptionMessage('A library name is written as a quoted string.');

        new CreateLoadableFunction(new Name('f'), LoadableResult::Real, new Text('41', EscapeRule::Backslash, Radix::Hexadecimal));
    }
}
