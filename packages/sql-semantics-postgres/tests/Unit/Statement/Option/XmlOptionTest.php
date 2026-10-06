<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;

#[CoversClass(XmlOption::class)]
#[Small]
final class XmlOptionTest extends TestCase
{
    public function testCasesSpellTheChoices(): void
    {
        self::assertSame(['DOCUMENT', 'CONTENT'], array_map(static fn (XmlOption $option): string => $option->value, XmlOption::cases()));
    }
}
