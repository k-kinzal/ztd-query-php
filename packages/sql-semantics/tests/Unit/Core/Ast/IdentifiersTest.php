<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use Tests\Contract\Resolved;

#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class IdentifiersTest extends TestCase
{
    public function testNamePreservesQuotedPostgresCaseAndFoldsUnquotedNames(): void
    {
        $builder = new Semantics(PostgreSqlDialect::PostgreSql);
        $schema = Resolved::of($builder->analyze('CREATE TABLE "Users" ("Id" INTEGER NOT NULL, SCORE INTEGER)', []));
        self::assertSame('Users', $schema->declarations[0]->name);
        self::assertSame(['Id', 'score'], array_column($schema->declarations[0]->columns, 'name'));
    }

    public function testEqualDistinguishesQuotedPostgresCase(): void
    {
        $builder = new Semantics(PostgreSqlDialect::PostgreSql);
        $schema = Resolved::of($builder->analyze('CREATE TABLE "Users" ("Id" INTEGER)', []));
        $this->expectException(SemanticException::class);
        Resolved::of($builder->analyze('CREATE TABLE "Users" ("Id" INTEGER, PRIMARY KEY (id))', []));
    }

    public function testPartsDoesNotSplitDotsInsideQuotedIdentifiers(): void
    {
        $builder = new Semantics(PostgreSqlDialect::PostgreSql);
        $schema = Resolved::of($builder->analyze('CREATE TABLE "a.b" ("c.d" INTEGER)', []));
        self::assertSame('a.b', $schema->declarations[0]->name);
        self::assertSame('c.d', $schema->declarations[0]->columns[0]->name);
    }

    public function testRelationEqualModelsMysqlTableAliasesSeparatelyFromColumns(): void
    {
        $names = new \SqlSemantics\Core\Ast\Identifiers(MySqlDialect::MySql);
        self::assertTrue($names->equal('Score', 'score'));
        self::assertFalse($names->relationEqual('Child', 'child'));
        $schema = Resolved::of((new Semantics(MySqlDialect::MySql))->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)', []));
        self::assertSame('users', $schema->declarations[0]->name);
    }

    #[DataProvider('providerIdentifiers')]
    public function testNameUnquotesEscapedDelimiters(Dialect $dialect, string $text, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Core\Ast\Identifiers($dialect))->name(new \SqlParser\Lexer\Token(1, 'ID', $text, 0)));
    }

    /**
     * @return iterable<string, array{Dialect, string, string}>
     */
    public static function providerIdentifiers(): iterable
    {
        yield 'postgres unquoted' => [PostgreSqlDialect::PostgreSql, 'USERS', 'users'];
        yield 'postgres quoted' => [PostgreSqlDialect::PostgreSql, '"Users"', 'Users'];
        yield 'escaped double quote' => [PostgreSqlDialect::PostgreSql, '"a""b"', 'a"b'];
        yield 'mysql unquoted' => [MySqlDialect::MySql, 'USERS', 'USERS'];
        yield 'mysql backtick' => [MySqlDialect::MySql, '`a``b`', 'a`b'];
        yield 'sqlite bracket' => [SqliteDialect::Sqlite, '[a b]', 'a b'];
    }
}
