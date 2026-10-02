<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Mutation\SqliteDelete;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SqliteDelete::class)]
#[Small]
final class SqliteDeleteTest extends TestCase
{
    public function testToStringKeepsItsTargetAndPredicate(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $target = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $scope = new Scope($catalog, $target);
        $column = new ColumnReference($scope, new Name('foo'));
        $delete = new SqliteDelete($scope, $column);
        self::assertSame($target, $delete->target);
        self::assertSame('DELETE FROM bar WHERE foo', $delete->toString());
    }

}
