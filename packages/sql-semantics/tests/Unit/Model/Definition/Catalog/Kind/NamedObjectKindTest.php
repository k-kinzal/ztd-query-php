<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\NamedObjectKind::class)]
#[Medium]
final class NamedObjectKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['ACCESS METHOD', 'DATABASE', 'EVENT TRIGGER', 'EXTENSION', 'FOREIGN DATA WRAPPER', 'LANGUAGE', 'PUBLICATION', 'ROLE', 'SCHEMA', 'SERVER', 'SUBSCRIPTION', 'TABLESPACE'], array_column(Kind\NamedObjectKind::cases(), 'value'));
    }
}
