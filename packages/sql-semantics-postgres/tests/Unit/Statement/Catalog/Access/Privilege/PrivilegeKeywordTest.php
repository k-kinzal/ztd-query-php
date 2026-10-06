<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::class)]
#[Small]
final class PrivilegeKeywordTest extends TestCase
{
    public function testPrivilegeIsTheNameTheServerReceives(): void
    {
        self::assertSame('alter system', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::AlterSystem->privilege());
        self::assertSame('select', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::Select->privilege());
        self::assertNull(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All->privilege());
    }
}
