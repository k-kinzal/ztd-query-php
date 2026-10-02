<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\SubqueryMatch::class)]
#[Small]
final class SubqueryMatchTest extends TestCase
{
    public function testChildrenRejectsReplacingScalarSelectionWithExistence(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $input = new C\Query\RowsDefinition(new C\Query\RowDefinition(new E\NullConstant()));
        $source = (new C\SubqueryConstruction())->derive($input, $scope);
        $this->expectException(InvariantViolation::class);
        (new V\SubqueryMatch())->children(new V\ScalarPair(new C\Subquery\ScalarQueryInput($input), new E\Subquery\SqliteExists($source), $scope));
    }

    public function testChildrenRejectsAnUnrelatedLexicalUseSite(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $scope = new Scope($catalog);
        $input = new C\Query\RowsDefinition(new C\Query\RowDefinition(new E\NullConstant()));
        $source = (new C\SubqueryConstruction())->derive($input, new Scope($catalog));
        $this->expectException(InvariantViolation::class);
        (new V\SubqueryMatch())->children(new V\ScalarPair(new C\Subquery\ScalarQueryInput($input), new E\Subquery\SqliteScalarSubquery($source), $scope));
    }

    public function testChildrenChecksAnActuallyStoredNestedPredicate(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $projection = new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new E\NullConstant()));
        $expected = new C\Query\SelectDefinition($projection, where: new E\NullConstant());
        $source = (new C\SubqueryConstruction())->derive(new C\Query\SelectDefinition($projection), $scope);
        $this->expectException(InvariantViolation::class);
        (new V\SubqueryMatch())->children(new V\ScalarPair(new C\Subquery\ExistsInput($expected), new E\Subquery\SqliteExists($source), $scope));
    }
}
