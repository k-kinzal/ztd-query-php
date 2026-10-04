<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;

#[CoversClass(ElementKind::class)]
#[Small]
final class ElementKindTest extends TestCase
{
    public function testCasesSpellTheElementKeywords(): void
    {
        self::assertSame(['COLUMN', 'PRIMARY KEY', 'FOREIGN KEY', 'INDEX', 'CHECK', 'CONSTRAINT'], array_column(ElementKind::cases(), 'value'));
    }
}
