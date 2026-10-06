<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FetchCursor::class)]
#[Medium]
final class FetchCursorTest extends TestCase
{
    public function testDeriveProgramResolvesTheCursorAndTheTargets(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(OUT a INT) BEGIN DECLARE b INT; DECLARE c CURSOR FOR SELECT 1, 2; FETCH c INTO a, B; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $fetch = $block->statements[0];
        self::assertInstanceOf(FetchCursor::class, $fetch);

        self::assertSame('c', $fetch->cursor->value);
        self::assertSame(['a', 'B'], array_map(static fn (Name $target): string => $target->value, $fetch->targets));
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsAnUndeclaredName')]
    public function testDeriveProgramReportsAnUndeclaredName(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsAnUndeclaredName(): iterable
    {
        yield 'an undefined cursor and variable' => ['CREATE PROCEDURE p() FETCH c INTO z', ['Undefined CURSOR: c', 'Undeclared variable: z']];
        yield 'a variable of an inner block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE x INT; END; FETCH c INTO x; END', ['Undeclared variable: x']];
        yield 'a variable in a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR SQLWARNING FETCH c INTO x, y; END', ['Undeclared variable: y']];
        yield 'a user variable' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH c INTO `@u`; END', ['Undeclared variable: @u']];
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
        yield 'NEXT FROM in 5.6' => ['mysql-5.6.51', 'create procedure p(x int) begin declare c cursor for select x; fetch next from c into x, x; end', 'CREATE PROCEDURE p(x INT) BEGIN DECLARE c CURSOR FOR SELECT x; FETCH c INTO x, x; END'];
        yield 'FROM in 5.7' => ['mysql-5.7.44', 'create procedure p(x int) begin declare c cursor for select x; fetch from c into x; end', 'CREATE PROCEDURE p(x INT) BEGIN DECLARE c CURSOR FOR SELECT x; FETCH c INTO x; END'];
        yield 'no keyword in 9.1' => ['mysql-9.1.0', 'create procedure p(x int) begin declare c cursor for select x; fetch c into x; end', 'CREATE PROCEDURE p(x INT) BEGIN DECLARE c CURSOR FOR SELECT x; FETCH c INTO x; END'];
    }

    public function testAFetchWithoutTargetsIsRejected(): void
    {
        $this->expectExceptionMessage('FETCH names at least one variable.');

        new FetchCursor(new Name('c'), []);
    }
}
