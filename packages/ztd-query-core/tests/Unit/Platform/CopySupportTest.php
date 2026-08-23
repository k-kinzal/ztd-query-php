<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\CopySupport;

#[CoversNothing]
final class CopySupportTest extends TestCase
{
    public function testDeclaresPlatformCopyContract(): void
    {
        $reflection = new \ReflectionClass(CopySupport::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('target'));
        self::assertTrue($reflection->hasMethod('selectSql'));
        self::assertTrue($reflection->hasMethod('insertSql'));
    }
}
