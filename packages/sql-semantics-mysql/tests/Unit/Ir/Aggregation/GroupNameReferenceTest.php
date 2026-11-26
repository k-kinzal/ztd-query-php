<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupNameReference;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;

#[CoversClass(GroupNameReference::class)]
#[Small]
final class GroupNameReferenceTest extends TestCase
{
    public function testRetainsTheBoundColumnAndBareSpelling(): void
    {
        $column = new ColumnReference(2);
        $name = new Identifier('ID');
        $reference = new GroupNameReference($column, $name);

        self::assertSame($column, $reference->column);
        self::assertSame($name, $reference->name);
    }
}
