<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Resolution\CommonBinding;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

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
