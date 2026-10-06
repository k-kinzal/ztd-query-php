<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;

#[CoversClass(CollationProvider::class)]
#[Small]
final class CollationProviderTest extends TestCase
{
    public function testReadIgnoresCase(): void
    {
        self::assertSame([CollationProvider::Builtin, CollationProvider::Icu, CollationProvider::Libc, null], [CollationProvider::read('BUILTIN'), CollationProvider::read('icu'), CollationProvider::read('Libc'), CollationProvider::read('glibc')]);
    }
}
