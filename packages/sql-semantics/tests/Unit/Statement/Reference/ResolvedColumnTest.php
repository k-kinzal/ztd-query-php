<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(ResolvedColumn::class)]
#[Small]
final class ResolvedColumnTest extends TestCase
{
    public function testReferenceKeepsItsExactOccurrenceTableAndColumn(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $scope = new Scope($catalog, new TableReference($catalog, $table->name, new Name('a')), new TableReference($catalog, $table->name, new Name('b')));
        $reference = $scope->resolve(new Name('id'), new QualifiedName(new Name('a')));
        self::assertInstanceOf(ResolvedColumn::class, $reference);
        self::assertSame($scope->tables[0], $reference->relation);
        self::assertSame($scope->catalog->tables[0], $reference->table);
        self::assertSame($scope->catalog->tables[0]->columns[0], $reference->column);
    }


}
