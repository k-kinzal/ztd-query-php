<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StaticPrivilege::class)]
#[Medium]
final class StaticPrivilegeTest extends TestCase
{
    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('GRANT INSERT (a, `b c`), REFERENCES ON t TO u', (new Inspection())->inspect('grant insert (a, `b c`), references on t to u')->toString());
    }
}
