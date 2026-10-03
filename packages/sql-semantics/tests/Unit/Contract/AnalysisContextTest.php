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

        $this->expectExceptionMessage('A declaration must belong to the language profile of the context.');

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
}
