<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\SchemaObjectKind::class)]
#[Medium]
final class SchemaObjectKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['COLLATION', 'CONVERSION', 'STATISTICS', 'TEXT SEARCH PARSER', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH TEMPLATE', 'TEXT SEARCH CONFIGURATION'], array_column(Kind\SchemaObjectKind::cases(), 'value'));
    }
}
