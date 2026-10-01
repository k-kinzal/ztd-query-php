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
use SqlSemantics\Statement\Reference\AmbiguousColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(AmbiguousColumn::class)]
#[Small]
final class AmbiguousColumnTest extends TestCase
{
    public function testAmbiguityKeepsSelfJoinOccurrencesSeparate(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $scope = new Scope($catalog, new TableReference($catalog, $table->name, new Name('a')), new TableReference($catalog, $table->name, new Name('b')));
        $reference = $scope->resolve(new Name('id'));
        self::assertInstanceOf(AmbiguousColumn::class, $reference);
        self::assertCount(2, $reference->matches);
        self::assertNotSame($reference->matches[0]->relation, $reference->matches[1]->relation);
        self::assertSame($reference->matches[0]->table, $reference->matches[1]->table);
        self::assertSame($reference->matches[0]->column, $reference->matches[1]->column);
    }
}
