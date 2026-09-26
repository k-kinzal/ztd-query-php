<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Core\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class FromBinderTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testJoinedBindsOnBeforeIntroducingThisJoinsNulls(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT b.score FROM users a LEFT JOIN users b ON a.id=b.id WHERE b.score > 0');
        self::assertInstanceOf(\SqlSemantics\Core\Model\Join::class, $statement->from);
        self::assertNotNull($statement->from->condition);
        self::assertSame(Nullability::NotNull, $statement->from->condition->operands[1]->nullability);
        self::assertNotNull($statement->where);
        self::assertSame(Nullability::MaybeNull, $statement->where->operands[0]->nullability);
        self::assertSame(['j0'], $statement->where->operands[0]->nullExtendedBy);
    }

    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j1', 'j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
    }

    public function testRelationFullJoinExtendsBothSides(): void
    {
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBindCrossJoinHasNoMatchPredicate(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id FROM users a CROSS JOIN users b');
        self::assertInstanceOf(\SqlSemantics\Core\Model\Join::class, $statement->from);
        self::assertSame(\SqlSemantics\Core\Model\JoinKind::Cross, $statement->from->kind);
        self::assertNull($statement->from->condition);
    }

    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $builder = new SchemaBuilder(SqliteDialect::Sqlite);
        $schema = $builder->build('CREATE TABLE main.users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT u.id FROM main.users AS u');
        self::assertSame('main', $statement->relations[0]->declaration->schema);
    }

    public function testTableRejectsAliasColumnLists(): void
    {
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT renamed FROM users AS u(renamed)');
    }

    public function testKindRecognizesRightAndRejectsNatural(): void
    {
        $builder = new SchemaBuilder(PostgreSqlDialect::PostgreSql);
        $tables = new \SqlSemantics\Core\Binding\TableResolver($builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'), new \SqlSemantics\Core\Ast\Identifiers(PostgreSqlDialect::PostgreSql), 'public');
        $reader = new \SqlSemantics\Core\Binding\FromBinder($tables, new \SqlSemantics\Core\Binding\IdentitySequence());
        $source = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1');
        self::assertSame(\SqlSemantics\Core\Model\JoinKind::Right, $reader->kind('RIGHT OUTER JOIN', $source));
        $this->expectException(SemanticException::class);
        $reader->kind('NATURAL JOIN', $source);
    }
}
