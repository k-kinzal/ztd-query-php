<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RelationTargets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(RelationTargets::class)]
#[Small]
final class RelationTargetsTest extends TestCase
{
    public function testResolveRecordsTheTableOfAMember(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        $trigger = new MemberName(new Name('g'), new QualifiedName(new Name('t')));
        $fact = (new RelationTargets())->resolve(ObjectKind::Trigger, $trigger, false, $derivation);
        self::assertInstanceOf(DeclaredTable::class, $fact?->table);
        self::assertSame($table, $fact->table->table);
        self::assertSame($fact, $derivation->facts()->relation($trigger));
    }

    public function testResolveIgnoresKindsThatNameNoRelation(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        self::assertNull((new RelationTargets())->resolve(ObjectKind::Index, new DottedName([new Name('t')]), false, $derivation));
        self::assertNull((new RelationTargets())->resolve(ObjectKind::Schema, new UnqualifiedName(new Name('t')), false, $derivation));
    }

    public function testDottedReportsAnImproperName(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        $name = new DottedName([new Name('a'), new Name('b'), new Name('c'), new Name('d')]);
        (new RelationTargets())->dotted($name, $name, false, $derivation);
        self::assertEquals([new ImproperName($name)], $derivation->facts()->diagnostics);
    }

    public function testRecordKeepsAnUndeclaredRelationOpen(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false));
        $target = new RelationTarget(new RelationReference(new QualifiedName(new Name('t'))));
        $fact = (new RelationTargets())->record($target, new QualifiedName(new Name('t')), false, $derivation);
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertFalse($fact->shape->complete());
    }

    public function testColumnReportsAMissingColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        (new RelationTargets())->column(new DottedName([new Name('t'), new Name('x')]), $derivation);
        self::assertEquals([new MissingColumn(new Name('x'), new QualifiedName(new Name('t')))], $derivation->facts()->diagnostics);
    }

    public function testMemberAcceptsADeclaredColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        $fact = (new RelationTargets())->record(new DottedName([new Name('t')]), new QualifiedName(new Name('t')), false, $derivation);
        (new RelationTargets())->member($fact, new Name('a'), new QualifiedName(new Name('t')), $derivation);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenamedReportsAnExistingColumn(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [$table], true));
        $fact = (new RelationTargets())->record(new DottedName([new Name('t')]), new QualifiedName(new Name('t')), false, $derivation);
        (new RelationTargets())->renamed($fact, new Name('a'), $derivation);
        self::assertSame(['column "a" already exists'], [$derivation->facts()->diagnostics[0]->message()]);
    }

    public function testHasAnswersNullWithoutADeclaration(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false));
        $fact = (new RelationTargets())->record(new DottedName([new Name('t')]), new QualifiedName(new Name('t')), false, $derivation);
        self::assertNull((new RelationTargets())->has($fact, new Name('a'), $derivation));
    }

    public function testSpelledJoinsTheParts(): void
    {
        self::assertSame('a.b', (new RelationTargets())->spelled([new Name('a'), new Name('b')]));
    }
}
