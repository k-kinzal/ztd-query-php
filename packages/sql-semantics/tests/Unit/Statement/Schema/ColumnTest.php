<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Column::class)]
#[Small]
final class ColumnTest extends TestCase
{
    public function testDeclarationPreservesItsTypeAndNullability(): void
    {
        $type = new TypeDescriptor(Builtin::Integer);
        $column = new Column(new Name('id'), $type, Nullability::NotNull);
        self::assertSame($type, $column->type);
        self::assertSame(Nullability::NotNull, $column->nullability);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($column));
    }
}
