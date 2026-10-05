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
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DropProgram::class)]
#[Medium]
final class DropProgramTest extends TestCase
{
    public function testDeriveStatementChangesNoContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $drop = $semantics->analyze('DROP TRIGGER IF EXISTS shop.tr', [$table]);

        self::assertSame([], $drop->facts->diagnostics);
        self::assertSame([], $drop->facts->declarations);
        self::assertNull($drop->facts->output);
    }

    /**
     * @return iterable<string, array{string, ProgramKind, bool, string|null, string}>
     */
    public static function providerDeriveStatementReadsTheDroppedProgram(): iterable
    {
        yield 'procedure' => ['DROP PROCEDURE IF EXISTS shop.p', ProgramKind::Procedure, true, 'shop', 'p'];
        yield 'function' => ['DROP FUNCTION f', ProgramKind::Function, false, null, 'f'];
        yield 'trigger' => ['DROP TRIGGER shop.tr', ProgramKind::Trigger, false, 'shop', 'tr'];
        yield 'event' => ['DROP EVENT IF EXISTS e', ProgramKind::Event, true, null, 'e'];
    }

    #[DataProvider('providerDeriveStatementReadsTheDroppedProgram')]
    public function testDeriveStatementReadsTheDroppedProgram(string $sql, ProgramKind $kind, bool $ifExists, ?string $schema, string $name): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;
        self::assertInstanceOf(DropProgram::class, $statement);

        self::assertSame($kind, $statement->kind);
        self::assertSame($ifExists, $statement->ifExists);
        self::assertSame($schema, $statement->name->schema?->value);
        self::assertSame($name, $statement->name->name->value);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDrop(): iterable
    {
        yield 'MySQL 5.6 procedure' => ['mysql-5.6.51', 'drop procedure if exists shop.p', 'DROP PROCEDURE IF EXISTS shop.p'];
        yield 'MySQL 5.7 function' => ['mysql-5.7.44', 'drop function f', 'DROP FUNCTION f'];
        yield 'MySQL 8.0 trigger' => ['mysql-8.0.44', 'drop trigger if exists shop.tr', 'DROP TRIGGER IF EXISTS shop.tr'];
        yield 'MySQL 9.1 event' => ['mysql-9.1.0', 'drop event e', 'DROP EVENT e'];
        yield 'MySQL 9.1 qualified function' => ['mysql-9.1.0', 'drop function if exists shop.f', 'DROP FUNCTION IF EXISTS shop.f'];
    }

    #[DataProvider('providerRenderWritesTheDrop')]
    public function testRenderWritesTheDrop(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedDrop(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new DropProgram(ProgramKind::Event, new QualifiedName(new Name('e'), new Name('shop')), true))->render($out);

        self::assertSame('DROP EVENT IF EXISTS shop.e', (new Lexical())->join($out->pieces()));
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A program name has at most a database qualifier.');

        new DropProgram(ProgramKind::Procedure, new QualifiedName(new Name('p'), new Name('shop'), new Name('def')));
    }
}
