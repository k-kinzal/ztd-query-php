<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;

#[CoversClass(LoadSource::class)]
#[Small]
final class LoadSourceTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['INFILE', 'URL', 'S3'], array_map(static fn (LoadSource $source): string => $source->value, LoadSource::cases()));
    }
}
