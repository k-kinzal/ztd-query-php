<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZtdQuery\Platform\ResultColumnTypeResolver;

#[CoversNothing]
final class ResultColumnTypeResolverTest extends TestCase
{
    public function testDeclaresResultMetadataResolutionContract(): void
    {
        $reflection = new ReflectionClass(ResultColumnTypeResolver::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('resolve'));
    }
}
