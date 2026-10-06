<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Resolution\TableLookup;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(TableLookup::class)]
#[Small]
final class TableLookupTest extends TestCase
{
    public function testFindResolvesTheOneDeclarationOfTheFirstSchemaThatHasIt(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $temp = new Table(new QualifiedName(new Name('t'), new Name('temp')), $profile, []);
        $main = new Table(new QualifiedName(new Name('t'), new Name('main')), $profile, []);
        $context = new AnalysisContext($profile, [new Name('temp'), new Name('main')], [$main, $temp]);

        $resolution = (new TableLookup())->find($context, new QualifiedName(new Name('t')));

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($temp, $resolution->table);
    }

    public function testFindReportsConflictingDeclarationsOfOneSchema(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $first = new Table(new QualifiedName(new Name('t')), $profile, []);
        $second = new Table(new QualifiedName(new Name('t')), $profile, []);
        $context = new AnalysisContext($profile, [new Name('main')], [$first, $second]);

        $resolution = (new TableLookup())->find($context, new QualifiedName(new Name('t')));

        self::assertInstanceOf(ConflictingTables::class, $resolution);
        self::assertSame([$first, $second], $resolution->candidates);
    }

    public function testFindIsMissingInACompleteContextAndUndeclaredInAnOpenOne(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $complete = new AnalysisContext($profile, [new Name('main')]);
        $open = new AnalysisContext($profile, [new Name('main')], [], false);

        $missing = (new TableLookup())->find($complete, new QualifiedName(new Name('t')));
        $undeclared = (new TableLookup())->find($open, new QualifiedName(new Name('t'), new Name('aux')));

        self::assertInstanceOf(MissingTable::class, $missing);
        self::assertInstanceOf(UndeclaredTable::class, $undeclared);
        self::assertSame('the declaration of relation aux.t', $undeclared->missing->describe());
    }

    public function testFindIsConditionalForALaterSchemaOfAnOpenContext(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $main = new Table(new QualifiedName(new Name('t'), new Name('main')), $profile, []);
        $open = new AnalysisContext($profile, [new Name('temp'), new Name('main')], [$main], false);
        $complete = new AnalysisContext($profile, [new Name('temp'), new Name('main')], [$main]);

        $conditional = (new TableLookup())->find($open, new QualifiedName(new Name('t')));
        $declared = (new TableLookup())->find($complete, new QualifiedName(new Name('t')));

        self::assertInstanceOf(ConditionalTable::class, $conditional);
        self::assertSame([$main], $conditional->candidates);
        self::assertSame('t', $conditional->missing->name->name->value);
        self::assertInstanceOf(DeclaredTable::class, $declared);
    }

    public function testFindSearchesOnlyTheSchemaAQualifiedNameWrites(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $main = new Table(new QualifiedName(new Name('t'), new Name('main')), $profile, []);
        $open = new AnalysisContext($profile, [new Name('temp'), new Name('main')], [$main], false);

        $qualified = (new TableLookup())->find($open, new QualifiedName(new Name('t'), new Name('main')));
        $elsewhere = (new TableLookup())->find($open, new QualifiedName(new Name('t'), new Name('temp')));

        self::assertInstanceOf(DeclaredTable::class, $qualified);
        self::assertInstanceOf(UndeclaredTable::class, $elsewhere);
    }
}
