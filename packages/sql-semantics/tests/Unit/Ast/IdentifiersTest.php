<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
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
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
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
final class IdentifiersTest extends TestCase
{
    public function testNamePreservesQuotedPostgresCaseAndFoldsUnquotedNames(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $schema = $builder->build('CREATE TABLE "Users" ("Id" INTEGER NOT NULL, SCORE INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT "Id", score FROM "Users"');
        self::assertSame(['Id', 'score'], array_column($statement->outputs, 'name'));
    }

    public function testEqualDistinguishesQuotedPostgresCase(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $schema = $builder->build('CREATE TABLE "Users" ("Id" INTEGER)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT id FROM "Users"');
    }

    public function testPartsDoesNotSplitDotsInsideQuotedIdentifiers(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $schema = $builder->build('CREATE TABLE "a.b" ("c.d" INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT "a.b"."c.d" FROM "a.b"');
        self::assertSame('a.b', $statement->relations[0]->declaration->name);
        self::assertSame('c.d', $statement->outputs[0]->name);
    }

    public function testRelationEqualModelsMysqlTableAliasesSeparatelyFromColumns(): void
    {
        $names = new \SqlSemantics\Ast\Identifiers(Dialect::MySql);
        self::assertTrue($names->equal('Score', 'score'));
        self::assertFalse($names->relationEqual('Child', 'child'));
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT CHILD.id FROM users child');
    }

    #[DataProvider('providerIdentifiers')]
    public function testNameUnquotesEscapedDelimiters(Dialect $dialect, string $text, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Ast\Identifiers($dialect))->name(new \SqlParser\Lexer\Token(1, 'ID', $text, 0)));
    }

    /**
     * @return iterable<string, array{Dialect, string, string}>
     */
    public static function providerIdentifiers(): iterable
    {
        yield 'postgres unquoted' => [Dialect::PostgreSql, 'USERS', 'users'];
        yield 'postgres quoted' => [Dialect::PostgreSql, '"Users"', 'Users'];
        yield 'escaped double quote' => [Dialect::PostgreSql, '"a""b"', 'a"b'];
        yield 'mysql unquoted' => [Dialect::MySql, 'USERS', 'USERS'];
        yield 'mysql backtick' => [Dialect::MySql, '`a``b`', 'a`b'];
        yield 'sqlite bracket' => [Dialect::Sqlite, '[a b]', 'a b'];
    }
}
