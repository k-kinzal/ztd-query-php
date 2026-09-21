<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class SchemaBuilderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'public', 'pg-17.2'])]
    #[TestWith([Dialect::MySql, '', 'mysql-8.4.7'])]
    #[TestWith([Dialect::Sqlite, 'main', 'sqlite-3.47.2'])]
    public function testBuildRetainsTheResolvedLanguageAndNamespace(Dialect $dialect, string $defaultSchema, string $grammarVersion): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER)');
        self::assertSame($dialect, $schema->dialect);
        self::assertSame($grammarVersion, $schema->grammarVersion);
        self::assertSame($defaultSchema, $schema->defaultSchema);
        self::assertSame($defaultSchema, $schema->tables[0]->schema);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBuildAcceptsSeparateSqlStringsInDeclarationOrder(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (id INTEGER)');
        self::assertSame(['a', 'b'], array_column($schema->tables, 'name'));
    }

    public function testBuildDoesNotAccumulatePreviousDeclarations(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $first = $builder->build('CREATE TABLE users (id INTEGER)');
        $empty = $builder->build();
        self::assertCount(1, $first->tables);
        self::assertSame([], $empty->tables);
        self::assertSame('integer', (new Binder($empty))->bind('SELECT 1')->outputs[0]->expression->type->name);
    }

    public function testBuildRejectsConflictingDeclarationsAcrossSqlStrings(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $builder->build('CREATE TABLE users (id INTEGER)', 'CREATE TABLE users (name TEXT)');
    }

    public function testBuildKeepsExplicitlyQualifiedDeclarations(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql, 'app', 'pg-17.2'))->build('CREATE TABLE public.users (id INTEGER)', 'CREATE TABLE users (id INTEGER)');
        self::assertSame(['public', 'app'], array_column($schema->tables, 'schema'));
        self::assertSame('pg-17.2', $schema->grammarVersion);
        self::assertSame($schema->tables[1], (new Binder($schema))->bind('SELECT id FROM users')->relations[0]->declaration);
    }

    public function testBuildPropagatesInvalidDdlSyntax(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        $builder->build('CREATE TABLE');
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsAnUnavailableGrammarRelease(Dialect $dialect): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported');
        new SchemaBuilder($dialect, grammarVersion: 'unavailable-release');
    }

}
