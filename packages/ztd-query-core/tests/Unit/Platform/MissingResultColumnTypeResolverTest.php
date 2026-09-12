<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ResultColumnTypeResolver;

#[CoversClass(MissingResultColumnTypeResolver::class)]
final class MissingResultColumnTypeResolverTest extends TestCase
{
    public function testResolveImplementsTheOptionalSessionResolverContract(): void
    {
        self::assertContains(
            ResultColumnTypeResolver::class,
            class_implements(MissingResultColumnTypeResolver::class),
        );
    }
}
