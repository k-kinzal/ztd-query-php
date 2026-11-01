<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\UncontextualizedCalls;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(UncontextualizedCalls::class)]
#[Medium]
final class UncontextualizedCallsTest extends TestCase
{
    public function testInactiveRetainsCteAndInvalidOuterCallSignatures(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('WITH c AS (SELECT ABS()) SELECT ``(ABS()), ABS()');
        $inactive = (new UncontextualizedCalls())->inactive($tree, $lowering);
        self::assertSame([true, true, true, false], array_map(static fn ($node): bool => isset($inactive[spl_object_id($node)]), $tree->find('function_call_generic')));
    }
}
