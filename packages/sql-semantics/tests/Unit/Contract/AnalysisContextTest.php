<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AnalysisContext::class)]
#[Medium]
final class AnalysisContextTest extends TestCase
{
    public function testDeclaredKeepsOneCopyOfTheSameDeclarationObject(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $table = new Table(new QualifiedName(new Name('t')), $profile, []);

        $context = new AnalysisContext($profile, [new Name('main')], [$table, $table]);

        self::assertSame([$table], $context->tables);
        self::assertSame([$table], $context->declared(new QualifiedName(new Name('t')), new Name('main')));
    }

    public function testDeclaredKeepsDistinctDeclarationsWithTheSameName(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $first = new Table(new QualifiedName(new Name('t')), $profile, []);
        $second = new Table(new QualifiedName(new Name('t')), $profile, []);

        $context = new AnalysisContext($profile, [new Name('main')], [$first, $second]);

        self::assertSame([$first, $second], $context->declared(new QualifiedName(new Name('t')), new Name('main')));
    }

    public function testDeclaredSearchesOneSchemaAndUsesTheDeclarationSchemaForUnqualifiedTables(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $unqualified = new Table(new QualifiedName(new Name('t')), $profile, []);
        $qualified = new Table(new QualifiedName(new Name('t'), new Name('aux')), $profile, []);

        $context = new AnalysisContext($profile, [new Name('temp'), new Name('main')], [$unqualified, $qualified], true, Comparison::Sensitive, Comparison::Sensitive, new Name('main'));

        self::assertSame('main', $context->declarationSchema->value);
        self::assertSame([$unqualified], $context->declared(new QualifiedName(new Name('t')), new Name('main')));
        self::assertSame([$qualified], $context->declared(new QualifiedName(new Name('t')), new Name('aux')));
        self::assertSame([], $context->declared(new QualifiedName(new Name('t')), new Name('temp')));
    }

    public function testDeclaredComparesNamesAsTheContextSays(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $table = new Table(new QualifiedName(new Name('Users')), $profile, []);
        $sensitive = new AnalysisContext($profile, [new Name('main')], [$table]);
        $insensitive = new AnalysisContext($profile, [new Name('main')], [$table], true, Comparison::AsciiInsensitive);

        self::assertSame([], $sensitive->declared(new QualifiedName(new Name('USERS')), new Name('main')));
        self::assertSame([$table], $insensitive->declared(new QualifiedName(new Name('USERS')), new Name('MAIN')));
    }

    public function testDeclaredFoldsTheNamesOfAFoldedSchema(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $folded = new Table(new QualifiedName(new Name('TABLES'), new Name('information_schema')), $profile, []);
        $exact = new Table(new QualifiedName(new Name('Users'), new Name('app')), $profile, []);
        $context = new AnalysisContext($profile, [new Name('app')], [$folded, $exact], true, Comparison::Sensitive, Comparison::Sensitive, null, null, ['information_schema']);

        self::assertSame([$folded], $context->declared(new QualifiedName(new Name('tables')), new Name('INFORMATION_SCHEMA')));
        self::assertSame([], $context->declared(new QualifiedName(new Name('users')), new Name('app')));
    }

    public function testFoldedTellsTheSchemasWhoseNamesAreFolded(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')], [], true, Comparison::Sensitive, Comparison::Sensitive, null, null, ['information_schema']);

        self::assertSame([true, false], [$context->folded('Information_Schema'), $context->folded('main')]);
        self::assertSame(['information_schema'], $context->withSession(null)->foldedSchemas);
    }

    public function testDeclaredMatchesACatalogOnlyWhenBothSidesWriteOne(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);
        $table = new Table(new QualifiedName(new Name('t'), new Name('main'), new Name('db')), $profile, []);
        $context = new AnalysisContext($profile, [new Name('main')], [$table]);

        self::assertSame([$table], $context->declared(new QualifiedName(new Name('t')), new Name('main')));
        self::assertSame([$table], $context->declared(new QualifiedName(new Name('t'), new Name('main'), new Name('db')), new Name('main')));
        self::assertSame([], $context->declared(new QualifiedName(new Name('t'), new Name('main'), new Name('other')), new Name('main')));
    }

    public function testDeclaredDefaultsTheDeclarationSchemaToTheFirstSearchedSchema(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);

        $context = new AnalysisContext($profile, [new Name('first'), new Name('second')]);

        self::assertSame('first', $context->declarationSchema->value);
        self::assertTrue($context->complete);
        self::assertSame([], $context->tables);
    }

    public function testDeclaredRefusesADeclarationOfAnotherProfile(): void
    {
        $sqlite = new LanguageProfile(GrammarRelease::Sqlite3472);
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql166), []);

        $this->expectExceptionMessage('A declaration must belong to the grammar release of the context.');

        new AnalysisContext($sqlite, [new Name('main')], [$table]);
    }

    public function testDeclaredRefusesAnEmptySearchPath(): void
    {
        $this->expectExceptionMessage('A context searches at least one schema.');

        new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), []);
    }

    public function testDeclaredFindsTheTableAStatementProvidedToTheFacade(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $context = $semantics->context([$table]);

        self::assertTrue($context->complete);
        self::assertSame([$table], $context->declared(new QualifiedName(new Name('T')), new Name('main')));
        self::assertFalse($semantics->context()->complete);
    }

    public function testWithSessionKeepsTheDeclarationsAndReplacesTheSession(): void
    {
        $semantics = new Semantics(MySqlDialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)')->declarations()[0];
        $session = new Settings(Collation::known('latin1_swedish_ci'));
        $context = $semantics->context([$table], false);

        $resolved = $context->withSession($session);

        self::assertNull($context->session);
        self::assertSame($session, $resolved->session);
        self::assertSame([$table], $resolved->tables);
        self::assertFalse($resolved->complete);
        self::assertSame($context->profile, $resolved->profile);
        self::assertSame($context->searchPath, $resolved->searchPath);
        self::assertSame($context->declarationSchema, $resolved->declarationSchema);
        self::assertSame($context->relationNames, $resolved->relationNames);
        self::assertSame($context->columnNames, $resolved->columnNames);
        self::assertNull($resolved->withSession(null)->session);
    }
}
