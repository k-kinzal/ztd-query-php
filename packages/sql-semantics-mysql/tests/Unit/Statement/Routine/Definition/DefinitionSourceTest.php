<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource;

#[CoversClass(DefinitionSource::class)]
#[Small]
final class DefinitionSourceTest extends TestCase
{
    public function testSourcePositionsKeepNumericBoundariesWithoutInputText(): void
    {
        $source = new DefinitionSource(17, [23, 29, 42], 49);
        self::assertSame([17, [23, 29, 42], 49], [$source->nameEnd, $source->typeEnds, $source->bodyRefusalOffset]);
        $absent = new DefinitionSource();
        self::assertSame([0, [], null], [$absent->nameEnd, $absent->typeEnds, $absent->bodyRefusalOffset]);
    }
}
