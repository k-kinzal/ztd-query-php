<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\TypeLookup;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(TypeLookup::class)]
#[Small]
final class TypeLookupTest extends TestCase
{
    public function testFindKnowsACatalogTypeInACompleteContext(): void
    {
        self::assertSame(Builtin::Text, (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName([new Name('text')])));
        self::assertSame(Builtin::Int4, (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), new DottedName([new Name('pg_catalog'), new Name('int4')])));
    }

    public function testFindDependsOnATemporaryRelationThatAnOpenContextCannotExclude(): void
    {
        $fact = (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), new DottedName([new Name('text')]));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertInstanceOf(UndeclaredRelation::class, $fact->missing[0]);
        self::assertSame('pg_temp', $fact->missing[0]->name->schema?->value);
    }

    public function testFindDependsOnASchemaSearchedBeforeTheCatalog(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['pg_temp', 'app', 'pg_catalog'], [], true);
        $fact = (new TypeLookup())->find($context, new DottedName([new Name('text')]));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertSame('the definition of data type text', $fact->missing[0]->describe());
    }

    public function testFindNamesAUserTypeAsTheMissingInput(): void
    {
        $fact = (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName([new Name('app'), new Name('money_amount')]));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertInstanceOf(UndeclaredDomain::class, $fact->missing[0]);
        $quoted = (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName([new Name('integer')]));
        self::assertInstanceOf(Dependent::class, $quoted);
    }

    public function testFindReportsTooManyPartsAndDependsOnTheCurrentDatabase(): void
    {
        $parts = [new Name('a'), new Name('b'), new Name('c'), new Name('d')];
        self::assertInstanceOf(Invalid::class, (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName($parts)));
        $fact = (new TypeLookup())->find((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName([new Name('db'), new Name('pg_catalog'), new Name('int4')]));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertInstanceOf(SessionState::class, $fact->missing[0]);
    }

    public function testColumnAnswersTheDeclaredTypeOfTheColumn(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int8)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        self::assertEquals(new Known(Builtin::Int8), (new TypeLookup())->column($context, new DottedName([new Name('t'), new Name('a')])));
        $missing = (new TypeLookup())->column($context, new DottedName([new Name('t'), new Name('b')]));
        self::assertInstanceOf(Invalid::class, $missing);
        self::assertInstanceOf(MissingColumn::class, $missing->cause);
    }

    public function testColumnDependsOnAnUndeclaredRelationAndReportsAMissingOne(): void
    {
        $open = (new TypeLookup())->column((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), new DottedName([new Name('t'), new Name('a')]));
        self::assertInstanceOf(Dependent::class, $open);
        $complete = (new TypeLookup())->column((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), new DottedName([new Name('t'), new Name('a')]));
        self::assertInstanceOf(Invalid::class, $complete);
        self::assertInstanceOf(MissingTable::class, $complete->cause);
    }

    public function testMemberDependsOnTheColumnsOfAnIncompleteDeclaration(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [], [], false);
        $fact = (new TypeLookup())->member((new Platform())->context($profile, null, [$table], true), new DeclaredTable($table), new Name('a'), new QualifiedName(new Name('t')));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertInstanceOf(IncompleteMembers::class, $fact->missing[0]);
    }
}
