<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOptionKeyword::class)]
#[Small]
final class DatabaseOptionKeywordTest extends TestCase
{
    public function testOptionJoinsTheKeywords(): void
    {
        self::assertSame('connection_limit', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOptionKeyword::ConnectionLimit->option());
    }
}
