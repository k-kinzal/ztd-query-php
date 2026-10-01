<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteBinary;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(SemanticGraph::class)]
#[UsesClass(Name::class)]
#[UsesClass(Savepoint::class)]
#[UsesClass(RollbackToSavepoint::class)]
#[UsesClass(TransactionName::class)]
#[Medium]
final class SemanticGraphTest extends TestCase
{
    public function testIsSemanticOperationRejectsAGrammarTreeEvenWhenItReconstructsSql(): void
    {
        $syntax = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1');
        self::assertSame('SELECT 1', $syntax->toString());
        self::assertFalse((new SemanticGraph())->isSemanticOperation($syntax));
    }

    public function testContainsOnlyValuesRejectsSyntaxInsideAnOtherwiseImmutableValue(): void
    {
        $declaration = (new Semantics(Dialect::Sqlite))->type('INTEGER');
        self::assertFalse((new SemanticGraph())->containsOnlyValues($declaration));
        self::assertTrue((new SemanticGraph())->containsOnlyValues($declaration->type));
    }

    public function testFingerprintComparesIdentifiersByValueRatherThanAllocation(): void
    {
        $name = new Name('mark');
        $shared = new RollbackToSavepoint($name, new TransactionName($name, true));
        $distinct = new RollbackToSavepoint($name, new TransactionName(new Name('mark'), true));
        self::assertSame($shared->toString(), $distinct->toString());
        self::assertSame((new SemanticGraph())->fingerprint($shared), (new SemanticGraph())->fingerprint($distinct));
        self::assertTrue((new SemanticGraph())->isSemanticOperation(new Savepoint($name)));
    }

    public function testDescribePreservesDeclarationIdentityAcrossTables(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $first = new Table(new QualifiedName(new Name('a')), $column);
        $shared = new Table(new QualifiedName(new Name('b')), $column);
        $distinct = new Table(new QualifiedName(new Name('b')), new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer)));
        $path = new SearchPath(new Name('main'));
        $sharedCatalog = new Catalog($path, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, true, null, null, $first, $shared);
        $distinctCatalog = new Catalog($path, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, true, null, null, $first, $distinct);
        $left = [];
        $right = [];
        self::assertNotSame((new SemanticGraph())->describe($sharedCatalog, $left), (new SemanticGraph())->describe($distinctCatalog, $right));
    }

    public function testConditionalAliasesKeepsOnlyDependenciesThatSurviveExpressionReduction(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $field = new Field(new NullConstant(), new Name('answer'));
        $alias = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        $conditional = new ColumnOrAlias(new ColumnReference($scope, new Name('answer')), $alias);
        $zero = new SqliteInteger(new UnsignedInteger('0'));
        $active = new SqliteBinary($zero, SqliteBinaryOperator::Add, $conditional);
        $eliminated = new SqliteBinary($zero, SqliteBinaryOperator::And, $conditional);
        self::assertSame([$conditional], (new SemanticGraph())->conditionalAliases($active));
        self::assertSame([], (new SemanticGraph())->conditionalAliases($eliminated));
    }
}
