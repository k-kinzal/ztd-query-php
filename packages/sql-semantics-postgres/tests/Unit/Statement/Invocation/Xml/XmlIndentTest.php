<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent;

#[CoversClass(XmlIndent::class)]
#[Small]
final class XmlIndentTest extends TestCase
{
    public function testCasesSpellTheOptions(): void
    {
        self::assertSame('INDENT', XmlIndent::Indent->value);
    }
}
