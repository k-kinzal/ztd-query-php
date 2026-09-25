<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
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
final class ProjectionBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testItemExpandsStarsAndPreservesDuplicateNames(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.*, b.score AS id FROM users a LEFT JOIN users b ON a.id=b.id');
        self::assertSame(['id', 'parent_id', 'score', 'id'], array_column($statement->outputs, 'name'));
        self::assertSame([0, 1, 2, 3], array_column($statement->outputs, 'ordinal'));
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindExpandsUnqualifiedStarInFromOrder(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT * FROM users a, users b');
        self::assertSame(['id', 'parent_id', 'score', 'id', 'parent_id', 'score'], array_column($statement->outputs, 'name'));
        self::assertSame('r0', $statement->outputs[0]->expression->binding?->relationId);
        self::assertSame('r1', $statement->outputs[3]->expression->binding?->relationId);
    }

    public function testStarRejectsAnUnknownQualifier(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT absent.* FROM users');
    }

    public function testItemResolvesBarePostgresUnknownLiteralsAsText(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind("SELECT NULL, 'hello'");
        self::assertSame('text', $statement->outputs[0]->expression->type->name);
        self::assertSame('text', $statement->outputs[1]->expression->type->name);
        self::assertSame(\SqlSemantics\Core\Type\Nullability::AlwaysNull, $statement->outputs[0]->expression->nullability);
    }

    public function testItemDecodesMysqlStringAliases(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind("SELECT score AS 'points' FROM users ORDER BY points");
        self::assertSame('points', $statement->outputs[0]->name);
        self::assertSame($statement->outputs[0]->expression, $statement->orderBy[0]->expression);
    }
}
