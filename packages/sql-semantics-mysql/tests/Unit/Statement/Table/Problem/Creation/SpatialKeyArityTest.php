<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SpatialKeyArity::class)]
#[Medium]
final class SpatialKeyArityTest extends TestCase
{
    public function testMessagePreservesTheOwningOperand(): void
    {
        $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart(new Name('a'));
        $problem = new SpatialKeyArity($part);
        self::assertSame($part, $problem->owner);
        self::assertSame('A spatial index requires exactly one key part.', $problem->message());
    }
}
