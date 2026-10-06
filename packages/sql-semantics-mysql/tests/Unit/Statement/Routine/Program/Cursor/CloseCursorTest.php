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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(CloseCursor::class)]
#[Medium]
final class CloseCursorTest extends TestCase
{
    public function testDeriveProgramReportsAnUndefinedCursor(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CLOSE c');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(CloseCursor::class, $create->body);

        self::assertSame('c', $create->body->cursor->value);
        self::assertSame(['Undefined CURSOR: c'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocks')]
    public function testDeriveProgramLooksTheCursorUpInTheEnclosingBlocks(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocks(): iterable
    {
        yield 'a cursor of the block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; CLOSE c; END', []];
        yield 'a cursor of an outer block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN CLOSE c; END; END', []];
        yield 'a cursor in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE C CURSOR FOR SELECT 1; CLOSE c; END', []];
        yield 'a cursor from a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR SQLWARNING CLOSE c; END', []];
        yield 'a cursor of an inner block' => ['CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CURSOR FOR SELECT 1; END; CLOSE c; END', ['Undefined CURSOR: c']];
        yield 'a cursor declared after a handler that uses it' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING CLOSE c; DECLARE c CURSOR FOR SELECT 1; END', ['Undefined CURSOR: c', 'Cursor declaration after handler declaration']];
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
        yield '5.6' => ['mysql-5.6.51', 'create procedure p() begin declare c cursor for select 1; close c; end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; CLOSE c; END'];
        yield '8.0' => ['mysql-8.0.44', 'create procedure p() close `c d`', 'CREATE PROCEDURE p() CLOSE `c d`'];
    }
}
