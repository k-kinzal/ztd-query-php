<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Coverage\GeneratorRevision;

#[CoversClass(GeneratorRevision::class)]
final class GeneratorRevisionTest extends TestCase
{
    public function testCurrentCachesAContentDigestRatherThanADevelopmentBranchName(): void
    {
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', GeneratorRevision::current());
        self::assertSame(GeneratorRevision::current(), GeneratorRevision::current());
    }
}
