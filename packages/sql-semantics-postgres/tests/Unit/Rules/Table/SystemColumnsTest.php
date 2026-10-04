<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns::class)]
#[Medium]
final class SystemColumnsTest extends TestCase
{
    public function testImplicitDeclaresTheSystemColumns(): void
    {
        self::assertSame([
          0 => 'tableoid oid',
          1 => 'cmax cid',
          2 => 'xmax xid',
          3 => 'cmin cid',
          4 => 'xmin xid',
          5 => 'ctid tid',
        ], array_map(static fn ($column): string => $column->column->name->value . ' ' . $column->column->type->name(), (new \SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns())->implicit()));
    }

    public function testReservedIsTrueForASystemColumnName(): void
    {
        self::assertSame([
          0 => true,
          1 => false,
        ], [(new \SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns())->reserved(new \SqlSemantics\Statement\Identifier\Name('xmin')), (new \SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns())->reserved(new \SqlSemantics\Statement\Identifier\Name('xmint'))]);
    }
}
