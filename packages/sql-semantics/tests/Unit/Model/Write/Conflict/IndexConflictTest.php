<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Write\Conflict\IndexConflict::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IndexConflictTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Write\Conflict\IndexConflict([]);
    }

    public function testKeepsItsKeysAndPredicate(): void
    {
        $key = \SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::Sqlite);
        $conflict = new \SqlSemantics\Model\Write\Conflict\IndexConflict([$key]);
        self::assertSame([$key], $conflict->keys);
        self::assertNull($conflict->predicate);
    }
}
