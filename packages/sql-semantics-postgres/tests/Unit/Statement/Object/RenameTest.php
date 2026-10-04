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
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(Rename::class)]
#[Medium]
final class RenameTest extends TestCase
{
    public function testDeriveStatementChecksTheRenamedColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $semantics = new Semantics(Dialect::PostgreSql);
        $missing = $semantics->analyze('ALTER TABLE ONLY t RENAME b TO c', [$table]);
        $taken = $semantics->analyze('ALTER TABLE IF EXISTS t RENAME COLUMN a TO a', [$table]);
        self::assertEquals([new MissingColumn(new Name('b'), new QualifiedName(new Name('t')))], $missing->facts->diagnostics);
        self::assertSame(['column "a" already exists'], [$taken->facts->diagnostics[0]->message()]);
    }

    public function testRenderWritesRolesAndAttributes(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['ALTER USER a RENAME TO b', 'ALTER TYPE t RENAME ATTRIBUTE a TO b CASCADE', 'ALTER TRIGGER g ON s.t RENAME TO h', 'ALTER LANGUAGE l RENAME TO m'],
            [$semantics->analyze('ALTER USER a RENAME TO b')->toString(), $semantics->analyze('ALTER TYPE t RENAME ATTRIBUTE a TO b CASCADE')->toString(), $semantics->analyze('ALTER TRIGGER g ON s.t RENAME TO h')->toString(), $semantics->analyze('ALTER PROCEDURAL LANGUAGE l RENAME TO m')->toString()],
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
}
