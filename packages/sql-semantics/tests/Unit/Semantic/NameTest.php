<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Semantic\Name;

#[\PHPUnit\Framework\Attributes\CoversClass(Name::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NameTest extends TestCase
{
    public function testToStringQuotesEmbeddedDelimiters(): void
    {
        self::assertSame('"a""b"', (new Name('a"b', '"'))->toString());
        self::assertSame('[a]]b]', (new Name('a]b', '['))->toString());
    }
}
