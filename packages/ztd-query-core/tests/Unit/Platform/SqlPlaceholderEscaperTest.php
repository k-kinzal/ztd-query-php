<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\SqlPlaceholderEscaper;

#[CoversNothing]
final class SqlPlaceholderEscaperTest extends TestCase
{
    public function testDeclaresPlatformPlaceholderEscapingContract(): void
    {
        $reflection = new \ReflectionClass(SqlPlaceholderEscaper::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('escape'));
    }
}
