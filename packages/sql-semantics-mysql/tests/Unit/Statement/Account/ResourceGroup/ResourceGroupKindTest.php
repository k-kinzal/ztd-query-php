<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;

#[CoversClass(ResourceGroupKind::class)]
#[Small]
final class ResourceGroupKindTest extends TestCase
{
    public function testCasesSpellTheTypes(): void
    {
        self::assertSame(['USER', 'SYSTEM'], array_column(ResourceGroupKind::cases(), 'value'));
    }
}
