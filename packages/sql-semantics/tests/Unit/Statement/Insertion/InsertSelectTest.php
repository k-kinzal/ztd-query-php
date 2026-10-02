<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Insertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\Arity;
use SqlSemantics\Statement\Insertion\InsertSelect;
use SqlSemantics\Statement\Insertion\Target;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(InsertSelect::class)]
#[Small]
final class InsertSelectTest extends TestCase
{
    public function testCorrelatedBodiesCannotImplementTheStatementRootContract(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $outer = new Scope($catalog);
        $query = new ScopedSelect($outer, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('foo'))))));
        self::assertFalse((new SemanticGraph())->isSemanticOperation($query));
        self::assertSame($outer, $query->scope->parent);
    }

    public function testRejectsAnEquivalentButDifferentContextSnapshot(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $other = new Catalog(new SearchPath(new Name('main')), complete: false);
        $query = new Select($other, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant()))));
        $this->expectException(\SqlSemantics\Statement\Validation\Failure\InvalidConstruction::class);
        new InsertSelect($target, $query);
    }

    public function testToStringPreservesTheDistinctSourceFormAndItsTarget(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))), new Name('foo'));
        $scope = new Scope($catalog);
        $operation = new InsertSelect($target, new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant(), new Name('foo'))))));
        self::assertSame('INSERT INTO bar (foo) SELECT NULL AS foo', $operation->toString());
        self::assertSame($target, $operation->target);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));
        self::assertSame(Arity::Matching, $operation->arity());
    }

    public function testArityDoesNotTreatAWidthMismatchAsAnUnknownSource(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))), new Name('a'), new Name('b'));
        $scope = new Scope($catalog);
        $operation = new InsertSelect($target, new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new NullConstant(), new Name('foo'))))));
        self::assertSame(Arity::Mismatch, $operation->arity());
    }
}
