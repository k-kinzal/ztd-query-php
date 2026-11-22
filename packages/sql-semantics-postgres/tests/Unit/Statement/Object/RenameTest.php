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
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedMember;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Rename::class)]
#[Medium]
final class RenameTest extends TestCase
{
    public function testDeriveStatementChecksTheRenamedColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $semantics = new Inspection();
        $missing = $semantics->diagnose('ALTER TABLE ONLY t RENAME b TO c', [$table]);
        $taken = $semantics->diagnose('ALTER TABLE IF EXISTS t RENAME COLUMN a TO a', [$table]);
        self::assertEquals([new MissingColumn(new Name('b'), new QualifiedName(new Name('t')))], $missing->facts->diagnostics);
        self::assertSame(['column "a" already exists'], [$taken->facts->diagnostics[0]->message()]);
    }

    public function testRenderWritesRolesAndAttributes(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['ALTER USER a RENAME TO b', 'ALTER TYPE t RENAME ATTRIBUTE a TO b CASCADE', 'ALTER TRIGGER g ON s.t RENAME TO h', 'ALTER LANGUAGE l RENAME TO m'],
            [$semantics->diagnose('ALTER USER a RENAME TO b')->toString(), $semantics->diagnose('ALTER TYPE t RENAME ATTRIBUTE a TO b CASCADE')->toString(), $semantics->diagnose('ALTER TRIGGER g ON s.t RENAME TO h')->toString(), $semantics->diagnose('ALTER PROCEDURAL LANGUAGE l RENAME TO m')->toString()],
        );
    }

    public function testRejectsARoleWithoutItsKeyword(): void
    {
        $this->expectExceptionMessage('A role is renamed with ROLE, USER or GROUP; nothing else is.');
        new Rename(ObjectKind::Role, new UnqualifiedName(new Name('r')), new Name('s'));
    }

    public function testRejectsIfExistsOfAnotherKind(): void
    {
        $this->expectExceptionMessage('ALTER ... RENAME accepts IF EXISTS for policies and relations only.');
        new Rename(ObjectKind::Schema, new UnqualifiedName(new Name('s')), new Name('t'), null, true);
    }

    public function testRejectsAPartOfAnotherKind(): void
    {
        $this->expectExceptionMessage('The renamed part belongs to the kind of the object.');
        new Rename(ObjectKind::Index, new RelationTarget(new RelationReference(new QualifiedName(new Name('i')))), new Name('j'), new RenamedMember(RenamedPart::Column, new Name('a')));
    }

    public function testRejectsADomainConstraintUnderIfExists(): void
    {
        $this->expectExceptionMessage('ALTER ... RENAME accepts IF EXISTS for policies and relations only.');
        new Rename(ObjectKind::Domain, new DottedName([new Name('d')]), new Name('c'), new RenamedMember(RenamedPart::Constraint, new Name('b')), true);
    }

    public function testRejectsABehaviorWithoutAnAttribute(): void
    {
        $this->expectExceptionMessage('CASCADE or RESTRICT is written only when renaming an attribute.');
        new Rename(ObjectKind::Type, new DottedName([new Name('t')]), new Name('u'), null, false, DropBehavior::Cascade);
    }

    public function testRejectsRoleWordForAnotherKind(): void
    {
        $this->expectExceptionMessage('A role is renamed with ROLE, USER or GROUP; nothing else is.');
        new Rename(ObjectKind::Schema, new UnqualifiedName(new Name('s')), new Name('t'), null, false, null, RoleWord::Group);
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Inspection();
        $context = [$semantics->diagnose('CREATE TABLE t (a int)'), $semantics->diagnose('CREATE SEQUENCE s')];
        self::assertSame(['"t" is not a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER VIEW t RENAME TO w', $context)->facts->diagnostics));
        self::assertSame(['cannot rename columns of relation "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER TABLE s RENAME CONSTRAINT c TO d', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER VIEW t RENAME COLUMN a TO b', $context)->facts->diagnostics));
    }
}
