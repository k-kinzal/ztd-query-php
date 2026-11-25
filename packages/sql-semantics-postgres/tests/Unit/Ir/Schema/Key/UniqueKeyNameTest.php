<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKeyName;

#[CoversClass(UniqueKeyName::class)]
#[Small]
final class UniqueKeyNameTest extends TestCase
{
    public function testAllocatePreservesOrderedIndexLabelsAndSkipsExistingNames(): void
    {
        $a = new Identifier('a');
        $b = new Identifier('b');
        $key = new UniqueKey([$a, $b], [$b, new Identifier('a1'), $a]);
        $taken = ['native_a_b_b1_a1_a2_key' => true];

        self::assertSame('native_a_b_b1_a1_a2_key1', UniqueKeyName::allocate(new Identifier('native'), $key, $taken));

    }

    public function testClipPreservesCompleteUtf8Characters(): void
    {
        self::assertSame('勘', UniqueKeyName::clip('勘定', 4));
    }

    public function testAttributeNamesRetainsDuplicatePositionWithAFreeSuffix(): void
    {
        $id = new Identifier('a');
        $key = new UniqueKey([$id], [new Identifier('a1'), $id, $id]);

        self::assertSame(['a', 'a1', 'a2', 'a3'], UniqueKeyName::attributeNames($key));
    }
}
