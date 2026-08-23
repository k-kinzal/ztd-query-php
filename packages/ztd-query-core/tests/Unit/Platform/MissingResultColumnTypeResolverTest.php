<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;

#[CoversClass(MissingResultColumnTypeResolver::class)]
final class MissingResultColumnTypeResolverTest extends TestCase
{
    public function testFailsWhenAPlatformResolverWasNotConfigured(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A database platform result column type resolver is required.');

        (new MissingResultColumnTypeResolver())->resolve([]);
    }
}
