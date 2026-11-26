<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Drop::class)]
#[Medium]
final class DropTest extends TestCase
{
    public function testDeriveStatementResolvesTheDroppedTables(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $operation = (new Inspection())->diagnose('DROP TABLE t, u', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Drop::class, $statement);
        $resolution = $operation->facts->relation($statement->objects[0])->table;
        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table, $resolution->table);
        self::assertSame(['Relation u does not exist.'], [$operation->facts->diagnostics[0]->message()]);
    }

    public function testDeriveStatementSkipsAMissingTableUnderIfExists(): void
    {
        $operation = (new Inspection())->diagnose('DROP VIEW IF EXISTS v', []);
        $statement = $operation->statement;
        self::assertInstanceOf(Drop::class, $statement);
        self::assertNull($operation->facts->relation($statement->objects[0])->table);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementReportsConcurrentDrops(): void
    {
        $operation = (new Inspection())->diagnose('DROP INDEX CONCURRENTLY i, j CASCADE');
        self::assertEquals([new ObjectProblem(ObjectProblemKind::ConcurrentMultiple), new ObjectProblem(ObjectProblemKind::ConcurrentCascade)], $operation->facts->diagnostics);
    }

    public function testRenderWritesEachKind(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['DROP LANGUAGE l', 'DROP TRIGGER IF EXISTS g ON t RESTRICT', 'DROP DOMAIN d, e', 'DROP OPERATOR FAMILY f USING btree', 'DROP TEXT SEARCH PARSER p'],
            [$semantics->diagnose('DROP PROCEDURAL LANGUAGE l')->toString(), $semantics->diagnose('DROP TRIGGER IF EXISTS g ON t RESTRICT')->toString(), $semantics->diagnose('DROP DOMAIN d, e')->toString(), $semantics->diagnose('DROP OPERATOR FAMILY f USING btree')->toString(), $semantics->diagnose('drop text search parser p')->toString()],
        );
    }

    public function testRejectsAWrongForm(): void
    {
        $this->expectExceptionMessage('DROP names each object as the grammar names objects of its kind.');
        new Drop(ObjectKind::Schema, [new DottedName([new Name('a'), new Name('b')])]);
    }

    public function testRejectsSeveralObjectsOfASingleKind(): void
    {
        $this->expectExceptionMessage('DROP names one object of this kind.');
        new Drop(ObjectKind::Cast, [new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair(new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new DottedName([new Name('a')]))), new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new DottedName([new Name('b')])))), new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair(new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new DottedName([new Name('c')]))), new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new DottedName([new Name('d')]))))]);
    }

    public function testRejectsConcurrentlyForAnotherKind(): void
    {
        $this->expectExceptionMessage('Only DROP INDEX is written CONCURRENTLY.');
        new Drop(ObjectKind::Schema, [new UnqualifiedName(new Name('s'))], false, null, true);
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Inspection();
        $context = [$semantics->diagnose('CREATE TABLE t (a int)'), $semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('DROP TABLE IF EXISTS v, t', $context)->facts->diagnostics));
        self::assertSame(['"t" is not a sequence'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('DROP SEQUENCE t', $context)->facts->diagnostics));
    }
}
