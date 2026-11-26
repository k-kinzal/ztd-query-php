<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;

#[CoversClass(IndexHeading::class)]
#[Small]
final class IndexHeadingTest extends TestCase
{
    public function testFieldSelectsThePhysicalSlotAfterAnUnusedUnrecognizedKind(): void
    {
        $owner = new IndexTarget(new Identifier('app'), new Identifier('orders'));
        $selected = new Identifier('value');
        $kind = ColumnKind::Int8;
        $heading = new IndexHeading($owner, [new Identifier('unused'), &$selected], [null, &$kind]);
        $selected = new Identifier('decoy');
        $kind = ColumnKind::Boolean;

        self::assertSame('value', $heading->field(1)->name->value);
        self::assertSame(ColumnKind::Int8, $heading->field(1)->kind);
    }
}
