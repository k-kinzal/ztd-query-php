<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

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
final class BinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
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
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (score INTEGER)');
        $sql = '/* source */ SELECT score FROM users';
        $statement = (new Binder($schema))->bind($sql);
        self::assertSame($sql, $statement->source->toString());
        self::assertSame($statement->source->find('columnref')[0], $statement->outputs[0]->expression->source);
        self::assertSame($schema->tables[0], $statement->relations[0]->declaration);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
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

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindUsesTheSchemasDefaultNamespace(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect, 'app'))->build('CREATE TABLE users (id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT id FROM users');
        self::assertSame('app', $statement->relations[0]->declaration->schema);
        self::assertSame($dialect, $statement->outputs[0]->expression->type->dialect);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testBindPropagatesSyntaxErrors(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build();
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new Binder($schema))->bind('SELECT FROM');
    }

    public function testBindRejectsMissingDeclarations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('1', $binder->bind('SELECT 1')->outputs[0]->expression->symbol);
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot resolve table');
        $binder->bind('SELECT id FROM missing');
    }
}
