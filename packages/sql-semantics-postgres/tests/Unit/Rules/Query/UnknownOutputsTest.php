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
        $field = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field(0, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()));
        self::assertEquals(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Unknown), (new \SqlSemantics\Platform\PostgreSql\Rules\Query\UnknownOutputs())->raw($field));
    }
}
