<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
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
final class FromBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testJoinedBindsOnBeforeIntroducingThisJoinsNulls(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT b.score FROM users a LEFT JOIN users b ON a.id=b.id WHERE b.score > 0');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertNotNull($statement->from->condition);
        self::assertSame(Nullability::NotNull, $statement->from->condition->operands[1]->nullability);
        self::assertNotNull($statement->where);
        self::assertSame(Nullability::MaybeNull, $statement->where->operands[0]->nullability);
        self::assertSame(['j0'], $statement->where->operands[0]->nullExtendedBy);
    }

    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j1', 'j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
    }

    public function testRelationFullJoinExtendsBothSides(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindCrossJoinHasNoMatchPredicate(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id FROM users a CROSS JOIN users b');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $statement->from);
        self::assertSame(\SqlSemantics\Model\JoinKind::Cross, $statement->from->kind);
        self::assertNull($statement->from->condition);
    }

    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $builder = new SchemaBuilder(Dialect::Sqlite);
        $schema = $builder->build('CREATE TABLE main.users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT u.id FROM main.users AS u');
        self::assertSame('main', $statement->relations[0]->declaration->schema);
    }

    public function testTableRejectsAliasColumnLists(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT renamed FROM users AS u(renamed)');
    }

    public function testKindRecognizesRightAndRejectsNatural(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $tables = new \SqlSemantics\Binding\TableResolver($builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $reader = new \SqlSemantics\Binding\FromBinder($tables, new \SqlSemantics\Binding\IdentitySequence());
        $source = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1');
        self::assertSame(\SqlSemantics\Model\JoinKind::Right, $reader->kind('RIGHT OUTER JOIN', $source));
        $this->expectException(SemanticException::class);
        $reader->kind('NATURAL JOIN', $source);
    }
}
