<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTestSpelling;

#[CoversClass(NullTestSpelling::class)]
#[Small]
final class NullTestSpellingTest extends TestCase
{
    public function testCasesAreTheTwoSpellings(): void
    {
        self::assertSame([NullTestSpelling::Keywords, NullTestSpelling::Postfix], NullTestSpelling::cases());
    }
}
