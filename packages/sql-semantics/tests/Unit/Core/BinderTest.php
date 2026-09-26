<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

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

#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
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
final class BinderTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBindSelfJoinPreservesOccurrenceIdentityAndNullProvenance(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT child.id, parent.score AS parent_score, COALESCE(parent.score, 0) AS effective_score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id');
        self::assertSame('s0', $statement->scopeId);
        self::assertSame(['r0', 'r1'], array_column($statement->relations, 'id'));
        self::assertSame($statement->relations[0]->declaration, $statement->relations[1]->declaration);
        self::assertSame(['id', 'parent_score', 'effective_score'], array_column($statement->outputs, 'name'));
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[1]->expression->nullability);
        self::assertSame(['j0'], $statement->outputs[1]->expression->nullExtendedBy);
        self::assertSame('integer', $statement->outputs[1]->expression->type->name);
        self::assertSame(Nullability::NotNull, $statement->outputs[2]->expression->nullability);
        self::assertSame([], $statement->outputs[2]->expression->nullExtendedBy);
        self::assertSame(['j0'], $statement->outputs[2]->expression->operands[0]->nullExtendedBy);
        self::assertSame('r1', $statement->outputs[2]->expression->lineage()[0]->relationId);
        self::assertSame(Nullability::NotNull, $statement->relations[1]->declaration->columns[2]->nullability);
    }

    public function testBindPreservesSourceTextAndExpressionNodeIdentity(): void
    {
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build('CREATE TABLE users (score INTEGER)');
        $sql = '/* source */ SELECT score FROM users';
        $statement = (new Binder($schema))->bind($sql);
        self::assertSame($sql, $statement->source->toString());
        self::assertSame($statement->source->find('columnref')[0], $statement->outputs[0]->expression->source);
        self::assertSame($schema->tables[0], $statement->relations[0]->declaration);
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBindResetsStatementIdentitiesWhenReusingTheBinder(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER)');
        $binder = new Binder($schema);
        $first = $binder->bind('SELECT a.id FROM users a LEFT JOIN users b ON a.id=b.id');
        $second = $binder->bind('SELECT id FROM users');
        self::assertSame(['r0', 'r1'], array_column($first->relations, 'id'));
        self::assertSame(['r0'], array_column($second->relations, 'id'));
        self::assertSame($schema->tables[0], $second->relations[0]->declaration);
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBindUsesTheSchemasDefaultNamespace(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect, 'app'))->build('CREATE TABLE users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT id FROM users');
        self::assertSame('app', $statement->relations[0]->declaration->schema);
        self::assertSame($dialect, $statement->outputs[0]->expression->type->dialect);
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBindPropagatesSyntaxErrors(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build();
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new Binder($schema))->bind('SELECT FROM');
    }

    public function testBindRejectsMissingDeclarations(): void
    {
        $binder = new Binder((new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build());
        self::assertSame('1', $binder->bind('SELECT 1')->outputs[0]->expression->symbol);
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot resolve table');
        $binder->bind('SELECT id FROM missing');
    }
}
