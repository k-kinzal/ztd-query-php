<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlStandalone;

#[CoversClass(XmlStandalone::class)]
#[Small]
final class XmlStandaloneTest extends TestCase
{
    public function testCasesSpellTheDeclarations(): void
    {
        self::assertSame(['YES', 'NO', 'NO VALUE'], array_map(static fn (XmlStandalone $standalone): string => $standalone->value, XmlStandalone::cases()));
    }
}
