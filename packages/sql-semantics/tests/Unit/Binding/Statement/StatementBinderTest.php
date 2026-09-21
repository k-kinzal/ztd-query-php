<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
final class StatementBinderTest extends TestCase
{
    public function testBindRetainsNonSelectOperation(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t SELECT id FROM t');
        self::assertSame('INSERT', $statement->kind);
        self::assertSame('t', $statement->targets[0]->declaration->name);
        self::assertSame('id', $statement->queries[0]->outputs[0]->name);
    }
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite])]
    public function testOperationResolvesMutationFollowingCtes(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (n INTEGER)');
        $sql = 'WITH x AS (SELECT 1 AS n) UPDATE t SET n=(SELECT n FROM x)';
        $tree = (new \SqlSemantics\Ast\DialectParser($dialect))->parse($sql);
        self::assertSame('UPDATE', \SqlSemantics\Binding\Statement\StatementBinder::operation($tree));
        $statement = (new Binder($schema))->bind($sql);
        self::assertSame('UPDATE', $statement->kind);
        self::assertSame(['x'], array_keys($statement->ctes));
        self::assertNotNull($statement->assignments['n']->query);
    }


    public function testBindRequiresOneStatement(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('bind() requires one statement; use bindAll() for a script.');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1; SELECT 2');
    }

}
