<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CommonBinding::class)]
#[Small]
final class CommonBindingTest extends TestCase
{
    public function testABindingTiesANameToItsDefinitionAndShape(): void
    {
        $definition = new Star();
        $shape = new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]);

        $binding = new CommonBinding(new Name('c'), $definition, $shape);

        self::assertSame('c', $binding->name->value);
        self::assertSame($definition, $binding->definition);
        self::assertSame($shape, $binding->shape);
    }
}
