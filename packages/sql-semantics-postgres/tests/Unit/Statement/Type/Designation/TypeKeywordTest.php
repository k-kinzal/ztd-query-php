<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;

#[CoversClass(TypeKeyword::class)]
#[Small]
final class TypeKeywordTest extends TestCase
{
    public function testBuiltinMapsEverySpellingToItsCatalogType(): void
    {
        self::assertSame(['int4', 'int4', 'int2', 'int8', 'float4', 'float8', 'bool', 'json'], array_map(static fn (TypeKeyword $keyword): string => $keyword->builtin()->value, TypeKeyword::cases()));
    }
}
