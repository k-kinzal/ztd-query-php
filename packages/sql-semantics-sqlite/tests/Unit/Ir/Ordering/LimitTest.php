<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;

#[CoversClass(Limit::class)]
#[Small]
final class LimitTest extends TestCase
{
    public function testKeepsCountAndOffsetInTheirSemanticRoles(): void
    {
        $count = new IntegerLiteral('-1');
        $offset = new Parameter('02');
        $limit = new Limit($count, $offset, true);

        self::assertSame($count, $limit->count);
        self::assertSame($offset, $limit->offset);
        self::assertTrue($limit->offsetFirst);
    }
}
