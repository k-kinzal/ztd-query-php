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
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(ColumnAssignment::class)]
#[Small]
final class ColumnAssignmentTest extends TestCase
{
    public function testToStringRetainsTheDestinationAndExpression(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $scope = new Scope($catalog, $target);
        $column = new ColumnReference($scope, new Name('foo'));
        $expression = new NullConstant();
        $assignment = new ColumnAssignment($column, $expression);
        self::assertSame($column, $assignment->column);
        self::assertSame($expression, $assignment->expression);
        self::assertSame('foo = NULL', $assignment->toString());
    }

}
