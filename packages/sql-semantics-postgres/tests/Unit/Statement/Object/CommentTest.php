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
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Comment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Comment::class)]
#[Medium]
final class CommentTest extends TestCase
{
    public function testDeriveStatementChecksTheColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $semantics = new Semantics();
        $missing = $semantics->inspect("COMMENT ON COLUMN public.t.b IS 'x'", [$table]);
        $unqualified = $semantics->inspect("COMMENT ON COLUMN b IS 'x'", [$table]);
        $long = $semantics->inspect("COMMENT ON COLUMN a.b.c.d.e IS 'x'", [$table]);
        self::assertEquals([new MissingColumn(new Name('b'), new QualifiedName(new Name('t'), new Name('public')))], $missing->facts->diagnostics);
        self::assertEquals([new ObjectProblem(ObjectProblemKind::UnqualifiedColumn)], $unqualified->facts->diagnostics);
        self::assertEquals([new ObjectProblem(ObjectProblemKind::ImproperRelation, 'a.b.c.d.e')], $long->facts->diagnostics);
    }

    public function testDeriveStatementAcceptsADeclaredColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $operation = (new Semantics())->inspect('COMMENT ON COLUMN t.a IS NULL', [$table]);
        self::assertSame([], $operation->facts->diagnostics);
        $statement = $operation->statement;
        self::assertInstanceOf(Comment::class, $statement);
        $resolution = $operation->facts->relation($statement->object)->table;
        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table, $resolution->table);
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ["COMMENT ON CONSTRAINT c ON DOMAIN d IS 'x'", "COMMENT ON LARGE OBJECT 5 IS 'x'", "COMMENT ON TRANSFORM FOR int4 LANGUAGE l IS 'x'", 'COMMENT ON OPERATOR - (NONE, int4) IS NULL', "COMMENT ON ROLE r IS ''"],
            [$semantics->inspect("COMMENT ON CONSTRAINT c ON DOMAIN d IS 'x'")->toString(), $semantics->inspect("COMMENT ON LARGE OBJECT 5 IS 'x'")->toString(), $semantics->inspect("COMMENT ON TRANSFORM FOR int4 LANGUAGE l IS 'x'")->toString(), $semantics->inspect('COMMENT ON OPERATOR - (NONE, int4) IS NULL')->toString(), $semantics->inspect("COMMENT ON ROLE r IS ''")->toString()],
        );
    }

    public function testRejectsAWrongForm(): void
    {
        $this->expectExceptionMessage('COMMENT names the object as the grammar names objects of its kind.');
        new Comment(ObjectKind::Table, new UnqualifiedName(new Name('t')), null);
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Semantics();
        $context = [$semantics->inspect('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('COMMENT ON TABLE v IS \'x\'', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('COMMENT ON VIEW v IS \'x\'', $context)->facts->diagnostics));
    }
}
