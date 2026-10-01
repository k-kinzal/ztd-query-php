<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\AmbiguousColumn;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Scope::class)]
#[Small]
final class ScopeTest extends TestCase
{
    public function testResolvePreservesOwnershipAndDistinguishesMissingNames(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $scope = new Scope($catalog, new TableReference($catalog, $table->name, new Name('a')), new TableReference($catalog, $table->name, new Name('b')));
        $reference = $scope->resolve(new Name('id'), new QualifiedName(new Name('a')));
        self::assertInstanceOf(ResolvedColumn::class, $reference);
        self::assertSame($scope->tables[0], $reference->relation);
        self::assertSame(MissingColumn::Value, $scope->resolve(new Name('absent')));
        self::assertSame(MissingColumn::Value, $scope->resolve(new Name('id'), new QualifiedName(new Name('users'))));
        self::assertTrue((new SemanticGraph())->containsOnlyValues($scope));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testResolveEmptyScopeNeverInventsAnOwner(bool $complete): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: $complete);
        self::assertSame(MissingColumn::Value, (new Scope($catalog))->resolve(new Name('id')));
    }

    public function testResolveMissingDeclarationsKeepsTheCandidateRelation(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $reference = (new Scope($catalog, $relation))->resolve(new Name('foo'));
        self::assertInstanceOf(CandidateColumn::class, $reference);
        self::assertSame([$relation], $reference->possibilities);
    }

    public function testResolvePartialContextDoesNotAssumeAKnownMatchIsUnique(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, false, null, null, $table);
        $known = new TableReference($catalog, $table->name);
        $candidate = new TableReference($catalog, new QualifiedName(new Name('other')));
        $reference = (new Scope($catalog, $known, $candidate))->resolve(new Name('id'));
        self::assertInstanceOf(CandidateColumn::class, $reference);
        self::assertSame($candidate, $reference->possibilities[0]);
        self::assertInstanceOf(ResolvedColumn::class, $reference->possibilities[1]);
        self::assertSame($column, $reference->possibilities[1]->column);
    }

    public function testResolveDoesNotHideDuplicateColumnDeclarations(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column, clone $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $reference = (new Scope($catalog, new TableReference($catalog, $table->name)))->resolve(new Name('id'));
        self::assertInstanceOf(AmbiguousColumn::class, $reference);
        self::assertCount(2, $reference->matches);
        self::assertNotSame($reference->matches[0]->column, $reference->matches[1]->column);
    }

}
