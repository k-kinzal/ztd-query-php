<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Relation\Foreign;

#[CoversClass(Foreign\TemplateProperty::class)]
#[Medium]
final class TemplatePropertyTest extends TestCase
{
    public function testSpellsEachPropertyAsItsKeyword(): void
    {
        self::assertSame(['COMMENTS', 'COMPRESSION', 'CONSTRAINTS', 'DEFAULTS', 'IDENTITY', 'GENERATED', 'INDEXES', 'STATISTICS', 'STORAGE', 'ALL'], array_column(Foreign\TemplateProperty::cases(), 'value'));
    }
}
