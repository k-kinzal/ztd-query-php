<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\JoinedInput::class)]
#[Small]
final class JoinedInputTest extends TestCase
{
    public function testVisibleHoldsTheRelationsInOrder(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\JoinedInput(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([])), []))->visible);
    }
}
