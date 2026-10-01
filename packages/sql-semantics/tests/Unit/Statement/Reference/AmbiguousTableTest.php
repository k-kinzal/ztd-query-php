<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\AmbiguousTable;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(AmbiguousTable::class)]
#[Small]
final class AmbiguousTableTest extends TestCase
{
    public function testConflictingDeclarationsCannotBeResolvedByChoosingOneColumn(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $first = new Table(new QualifiedName(new Name('users')), $column);
        $second = new Table($first->name);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $first, $second);
        $relation = new TableReference($catalog, $first->name);
        $expression = new ColumnReference(new Scope($catalog, $relation), new Name('id'));
        self::assertInstanceOf(AmbiguousTable::class, $expression->resolution);
        self::assertSame([$relation], $expression->resolution->relations);
        self::assertSame([$first, $second], $relation->declarations);
        self::assertSame(Invalid::AmbiguousTable, $expression->type());
    }
}
