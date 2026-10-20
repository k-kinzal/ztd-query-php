<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint\Index;

use MySqlMemory\Command\Definition\Constraint\Index\Keys;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Keys::class)]
#[Medium]
final class KeysTest extends TestCase
{
    public function testBuildPreservesOrderedSlotsPrefixesAndNames(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b VARCHAR(10),KEY idx(a,b(3)),CONSTRAINT u UNIQUE(b))');
        $keys = $session->instance->dictionary->table('d', 't')?->definition->keys ?? [];
        self::assertSame(['idx', 'u'], array_column($keys, 'name'));
        self::assertSame([[0, 1], [1]], array_column($keys, 'columns'));
        self::assertSame([[null, 3], [null]], array_column($keys, 'prefixes'));
    }
}
