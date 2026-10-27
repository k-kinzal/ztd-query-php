<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(OpenCursor::class)]
#[Medium]
final class OpenCursorTest extends TestCase
{
    public function testDeriveProgramReportsAnUndefinedCursor(): void
    {
        $this->expectExceptionMessage('Undefined CURSOR: c');
        (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() OPEN c');
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocks')]
    public function testDeriveProgramLooksTheCursorUpInTheEnclosingBlocks(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocks(): iterable
    {
        yield 'a cursor of the block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; OPEN c; END', []];
        yield 'a cursor of an outer block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN OPEN c; END; END', []];
        yield 'a cursor in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE C CURSOR FOR SELECT 1; OPEN c; END', []];
        yield 'a cursor from a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR SQLWARNING OPEN c; END', []];
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
        yield '5.6' => ['mysql-5.6.51', 'create procedure p() begin declare c cursor for select 1; open c; end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; OPEN c; END'];
        yield '8.0' => ['mysql-8.0.44', 'create procedure p() begin declare `c d` cursor for select 1; open `c d`; end', 'CREATE PROCEDURE p() BEGIN DECLARE `c d` CURSOR FOR SELECT 1; OPEN `c d`; END'];
    }


    #[DataProvider('providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocksRejectsBeforePublication')]
    public function testDeriveProgramLooksTheCursorUpInTheEnclosingBlocksRejectsBeforePublication(string $sql, string $message): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage($message);
        (new Semantics(Dialect::MySql))->analyze($sql);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveProgramLooksTheCursorUpInTheEnclosingBlocksRejectsBeforePublication(): iterable
    {
        yield 'a cursor of an inner block' => ['CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CURSOR FOR SELECT 1; END; OPEN c; END', 'Undefined CURSOR: c'];
        yield 'a cursor declared after a handler that uses it' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING OPEN c; DECLARE c CURSOR FOR SELECT 1; END', 'Undefined CURSOR: c'];
    }
    public function testRenderRejectsTheUnboundQuotedCursorBeforePublication(): void
    {
        $this->expectExceptionMessage('Undefined CURSOR: c d');
        (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('create procedure p() open `c d`');
    }
}
