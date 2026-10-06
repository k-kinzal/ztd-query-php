<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(CursorDeclaration::class)]
#[Medium]
final class CursorDeclarationTest extends TestCase
{
    public function testRenderWritesTheNameAndTheQuery(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT x; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $cursor = $block->declarations[1];
        self::assertInstanceOf(CursorDeclaration::class, $cursor);

        self::assertSame('c', $cursor->name->value);
        self::assertInstanceOf(Select::class, $cursor->query);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT x; END', $operation->toString());
    }

    public function testRenderWritesAQueryThatSeesOnlyTheVariablesDeclaredBefore(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT y; END');

        self::assertSame(['Column y does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT y; END', $operation->toString());
    }

    #[DataProvider('providerRenderWritesTheDeclaration')]
    public function testRenderWritesTheDeclaration(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDeclaration(): iterable
    {
        yield 'a parenthesized union in 5.7' => ['mysql-5.7.44', 'create procedure p() begin declare c cursor for (select 1) union (select 2); end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR (SELECT 1) UNION (SELECT 2); END'];
        yield 'a WITH query in 8.0' => ['mysql-8.0.44', 'create procedure p() begin declare c cursor for with q as (select 1 as a) select a from q; end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR WITH q AS (SELECT 1 AS a) SELECT a FROM q; END'];
        yield 'VALUES in 9.1' => ['mysql-9.1.0', 'create procedure p() begin declare c cursor for values row(1); end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR VALUES ROW(1); END'];
    }
}
