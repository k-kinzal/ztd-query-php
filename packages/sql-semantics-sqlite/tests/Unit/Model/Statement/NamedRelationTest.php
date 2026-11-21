<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\NamedRelation;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(NamedRelation::class)]
#[Medium]
final class NamedRelationTest extends TestCase
{
    public function testNameAndAliasAreReadDecoded(): void
    {
        $input = (new Semantics())->analyze('SELECT a FROM main."order items" AS "x y"')->singleNamedInput();

        self::assertSame('order items', $input->name()->name->value);
        self::assertSame('main', $input->name()->schema?->value);
        self::assertSame('x y', $input->alias()?->value);
    }

    public function testAliasIsNullWhenTheOccurrenceIsNotRenamed(): void
    {
        $input = (new Semantics())->analyze('SELECT a FROM t')->singleNamedInput();

        self::assertNull($input->alias());
        self::assertNull($input->name()->schema);
    }
}
