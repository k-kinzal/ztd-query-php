<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::class)]
#[Small]
final class FunctionRoleTest extends TestCase
{
    public function testOptionIsLowerCase(): void
    {
        self::assertSame('handler', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Handler->option());
    }
}
