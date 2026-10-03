<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Insertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\Arity;
use SqlSemantics\Statement\Insertion\InsertRows;
use SqlSemantics\Statement\Insertion\Target;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(InsertRows::class)]
#[Small]
final class InsertRowsTest extends TestCase
{
    public function testToStringPreservesTheDistinctSourceFormAndItsTarget(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))), new Name('foo'));
        $scope = new Scope($catalog);
        $operation = new InsertRows($target, new Rows(new Row($scope, new NullConstant())));
        self::assertSame('INSERT INTO bar (foo) VALUES (NULL)', $operation->toString());
        self::assertSame($target, $operation->target);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));
        self::assertSame(Arity::Matching, $operation->arity());
    }

    public function testArityDoesNotTreatAWidthMismatchAsAnUnknownSource(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))), new Name('a'), new Name('b'));
        $scope = new Scope($catalog);
        $operation = new InsertRows($target, new Rows(new Row($scope, new NullConstant())));
        self::assertSame(Arity::Mismatch, $operation->arity());
    }
}
