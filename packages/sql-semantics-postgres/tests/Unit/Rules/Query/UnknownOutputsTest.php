<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\UnknownOutputs::class)]
#[Small]
final class UnknownOutputsTest extends TestCase
{
    public function testRawKeepsAnUntypedLiteralUnknown(): void
    {
        $field = new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()));
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Unknown), (new \SqlSemantics\Platform\PostgreSql\Rules\Query\UnknownOutputs())->raw($field));
    }
}
