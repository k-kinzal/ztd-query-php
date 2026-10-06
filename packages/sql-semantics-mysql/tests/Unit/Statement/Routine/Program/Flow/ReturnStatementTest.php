<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ReturnStatement::class)]
#[Medium]
final class ReturnStatementTest extends TestCase
{
    public function testDeriveProgramResolvesTheParameter(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
        $create = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $create);
        $return = $create->body;
        self::assertInstanceOf(ReturnStatement::class, $return);
        self::assertInstanceOf(ColumnUse::class, $return->value);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($return->value)->resolution);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsAReturnOutsideAFunction')]
    public function testDeriveProgramReportsAReturnOutsideAFunction(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsAReturnOutsideAFunction(): iterable
    {
        yield 'a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN DECLARE x INT DEFAULT 2; RETURN x; END', []];
        yield 'an unknown name in a function' => ['CREATE FUNCTION f(a INT) RETURNS INT RETURN b', ['Column b does not exist.']];
        yield 'a procedure' => ['CREATE PROCEDURE p() RETURN 1', ['RETURN is only allowed in a FUNCTION']];
        yield 'a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RETURN 1', ['RETURN is only allowed in a FUNCTION']];
        yield 'an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO RETURN 1', ['RETURN is only allowed in a FUNCTION']];
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'a parameter in 5.6' => ['mysql-5.6.51', 'create function f(a int) returns int return a', 'CREATE FUNCTION f(a INT) RETURNS INT RETURN a'];
        yield 'a subquery in 8.0' => ['mysql-8.0.44', 'create function f(a int) returns int return (select a + 1)', 'CREATE FUNCTION f(a INT) RETURNS INT RETURN (SELECT a + 1)'];
        yield 'in a block in 9.1' => ['mysql-9.1.0', 'create function f() returns int begin return 1; end', 'CREATE FUNCTION f() RETURNS INT BEGIN RETURN 1; END'];
    }
}
