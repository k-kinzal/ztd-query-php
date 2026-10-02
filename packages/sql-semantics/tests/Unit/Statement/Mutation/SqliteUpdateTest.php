<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Mutation\ColumnAssignment;
use SqlSemantics\Statement\Mutation\SqliteUpdate;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SqliteUpdate::class)]
#[Small]
final class SqliteUpdateTest extends TestCase
{
    public function testToStringKeepsTheRequestedAssignments(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $scope = new Scope($catalog, $target);
        $column = new ColumnReference($scope, new Name('foo'));
        $assignment = new ColumnAssignment($column, new NullConstant());
        $update = new SqliteUpdate($target, $scope, assignments: $assignment);
        self::assertSame('UPDATE bar SET foo = NULL', $update->toString());
        self::assertSame([$assignment], $update->assignments);
    }

    public function testEffectiveAssignmentsKeepsTheLastRequestForEachDestination(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $scope = new Scope($catalog, $target);
        $column = new ColumnReference($scope, new Name('foo'));
        $first = new ColumnAssignment($column, new NullConstant());
        $second = new ColumnAssignment($column, $column);
        $update = new SqliteUpdate($target, $scope, assignments: $first, another: $second);
        self::assertSame([$first, $second], $update->assignments);
        self::assertSame([$second], $update->effectiveAssignments());
    }

}
