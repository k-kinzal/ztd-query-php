<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFormatter\Core\FormattingException;

#[CoversClass(FormattingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class FormattingExceptionTest extends TestCase
{
    public function testGetPreviousRetainsVerificationCause(): void
    {
        $cause = new RuntimeException('Invalid token boundary');
        $error = new FormattingException('Verification failed', 0, $cause);
        self::assertSame($cause, $error->getPrevious());
        self::assertSame('Verification failed', $error->getMessage());
    }
}
