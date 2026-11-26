<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Manipulation\CopyFacts::class)]
#[Medium]
final class CopyFactsTest extends TestCase
{
    public function testTableReportsWhereWithTo(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->diagnose('COPY t TO STDOUT WHERE a = 1', [$t, $u]);
        self::assertSame(['WHERE clause not allowed with COPY TO'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testQueryReportsSelectInto(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->diagnose('COPY (SELECT 1 INTO x) TO STDOUT', [$t, $u]);
        self::assertSame(['COPY (SELECT INTO) is not supported'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testColumnsReportsUnknownAndRepeatedColumns(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->diagnose('COPY t (a, z, a) FROM STDIN', [$t, $u]);
        self::assertSame(['column "z" of relation "t" does not exist', 'column "a" specified more than once'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testProgramReportsTheClient(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->diagnose('COPY t FROM PROGRAM STDIN', [$t, $u]);
        self::assertSame(['STDIN/STDOUT not allowed with PROGRAM'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testKindReportsARelationCopyCannotRead(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [$semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a'), $semantics->diagnose('CREATE SEQUENCE s')];
        self::assertSame(['cannot copy from view "v"'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('COPY v TO STDOUT', $context)->facts->diagnostics));
        self::assertSame(['cannot copy to sequence "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('COPY s FROM STDIN', $context)->facts->diagnostics));
        self::assertSame([], $semantics->diagnose('COPY v FROM STDIN', $context)->facts->diagnostics);
    }

    public function testColumnsReportsAGeneratedColumn(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $table = $semantics->diagnose('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        self::assertSame(['column "b" is a generated column'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('COPY g (c, b) TO STDOUT', $table->declarations())->facts->diagnostics));
    }

    public function testGeneratedAnswersTheGeneratedColumns(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-16.6');
        $table = $semantics->diagnose('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c text)');
        self::assertSame(['FORCE_QUOTE column "c" not referenced by COPY'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('COPY g (a) TO STDOUT CSV FORCE QUOTE c', $table->declarations())->facts->diagnostics));
        self::assertSame(['column "b" is a generated column'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('COPY g TO STDOUT CSV FORCE QUOTE b, c', $table->declarations())->facts->diagnostics));
    }
}
