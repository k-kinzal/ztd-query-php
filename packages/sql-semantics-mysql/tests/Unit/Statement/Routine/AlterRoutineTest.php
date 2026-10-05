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
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AlterRoutine::class)]
#[Medium]
final class AlterRoutineTest extends TestCase
{
    public function testDeriveStatementChangesNoContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $alter = $semantics->analyze("ALTER PROCEDURE shop.p COMMENT 'c' MODIFIES SQL DATA", [$table]);

        self::assertSame([], $alter->facts->diagnostics);
        self::assertSame([], $alter->facts->declarations);
        self::assertNull($alter->facts->output);
    }

    public function testDeriveStatementReadsTheKindTheNameAndTheCharacteristics(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER FUNCTION shop.f NO SQL SQL SECURITY INVOKER');
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);

        self::assertSame(ProgramKind::Function, $statement->kind);
        self::assertSame('shop', $statement->name->schema?->value);
        self::assertSame('f', $statement->name->name->value);
        self::assertEquals([new DataAccess(AccessLevel::NoSql), new SqlSecurity(SecurityContext::Invoker)], $statement->characteristics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheAlteration(): iterable
    {
        yield 'MySQL 5.6 every characteristic' => [
            'mysql-5.6.51',
            "alter procedure shop.p comment 'x' language sql sql security invoker contains sql",
            "ALTER PROCEDURE shop.p COMMENT 'x' LANGUAGE SQL SQL SECURITY INVOKER CONTAINS SQL",
        ];
        yield 'MySQL 5.7 function' => ['mysql-5.7.44', 'alter function f modifies sql data', 'ALTER FUNCTION f MODIFIES SQL DATA'];
        yield 'MySQL 8.0 without characteristics' => ['mysql-8.0.44', 'alter procedure p', 'ALTER PROCEDURE p'];
        yield 'MySQL 9.1 two access levels' => ['mysql-9.1.0', 'alter function shop.f no sql reads sql data', 'ALTER FUNCTION shop.f NO SQL READS SQL DATA'];
    }

    #[DataProvider('providerRenderWritesTheAlteration')]
    public function testRenderWritesTheAlteration(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedAlteration(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new AlterRoutine(ProgramKind::Procedure, new QualifiedName(new Name('p'), new Name('shop')), [new SqlSecurity(SecurityContext::Definer)]))->render($out);

        self::assertSame('ALTER PROCEDURE shop.p SQL SECURITY DEFINER', (new Lexical())->join($out->pieces()));
    }

    public function testATriggerIsRejected(): void
    {
        $this->expectExceptionMessage('ALTER of a routine names a procedure or a function.');

        new AlterRoutine(ProgramKind::Trigger, new QualifiedName(new Name('tr')));
    }

    public function testAnEventIsRejected(): void
    {
        $this->expectExceptionMessage('ALTER of a routine names a procedure or a function.');

        new AlterRoutine(ProgramKind::Event, new QualifiedName(new Name('e')));
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A routine name has at most a database qualifier.');

        new AlterRoutine(ProgramKind::Function, new QualifiedName(new Name('f'), new Name('shop'), new Name('def')));
    }

    public function testADeterministicCharacteristicIsRejected(): void
    {
        $this->expectExceptionMessage('ALTER of a routine has no DETERMINISTIC characteristic.');

        new AlterRoutine(ProgramKind::Function, new QualifiedName(new Name('f')), [new DataAccess(AccessLevel::NoSql), new Determinism(true)]);
    }
}
