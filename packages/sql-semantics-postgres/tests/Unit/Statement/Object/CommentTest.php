<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Comment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(Comment::class)]
#[Medium]
final class CommentTest extends TestCase
{
    public function testDeriveStatementChecksTheColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $semantics = new Semantics(Dialect::PostgreSql);
        $missing = $semantics->analyze("COMMENT ON COLUMN public.t.b IS 'x'", [$table]);
        $unqualified = $semantics->analyze("COMMENT ON COLUMN b IS 'x'", [$table]);
        $long = $semantics->analyze("COMMENT ON COLUMN a.b.c.d.e IS 'x'", [$table]);
        self::assertEquals([new MissingColumn(new Name('b'), new QualifiedName(new Name('t'), new Name('public')))], $missing->facts->diagnostics);
        self::assertEquals([new ObjectProblem(ObjectProblemKind::UnqualifiedColumn)], $unqualified->facts->diagnostics);
        self::assertEquals([new ObjectProblem(ObjectProblemKind::ImproperRelation, 'a.b.c.d.e')], $long->facts->diagnostics);
    }

    public function testDeriveStatementAcceptsADeclaredColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('COMMENT ON COLUMN t.a IS NULL', [$table]);
        self::assertSame([], $operation->facts->diagnostics);
        $statement = $operation->statement;
        self::assertInstanceOf(Comment::class, $statement);
        $resolution = $operation->facts->relation($statement->object)->table;
        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table, $resolution->table);
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ["COMMENT ON CONSTRAINT c ON DOMAIN d IS 'x'", "COMMENT ON LARGE OBJECT 5 IS 'x'", "COMMENT ON TRANSFORM FOR int4 LANGUAGE l IS 'x'", 'COMMENT ON OPERATOR - (NONE, int4) IS NULL', "COMMENT ON ROLE r IS ''"],
            [$semantics->analyze("COMMENT ON CONSTRAINT c ON DOMAIN d IS 'x'")->toString(), $semantics->analyze("COMMENT ON LARGE OBJECT 5 IS 'x'")->toString(), $semantics->analyze("COMMENT ON TRANSFORM FOR int4 LANGUAGE l IS 'x'")->toString(), $semantics->analyze('COMMENT ON OPERATOR - (NONE, int4) IS NULL')->toString(), $semantics->analyze("COMMENT ON ROLE r IS ''")->toString()],
        );
    }

    public function testRejectsAWrongForm(): void
    {
        $this->expectExceptionMessage('COMMENT names the object as the grammar names objects of its kind.');
        new Comment(ObjectKind::Table, new UnqualifiedName(new Name('t')), null);
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('COMMENT ON TABLE v IS \'x\'', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('COMMENT ON VIEW v IS \'x\'', $context)->facts->diagnostics));
    }
}
