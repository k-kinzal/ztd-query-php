<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Relation\Foreign\TableTemplate;
use SqlSemantics\Model\Definition\Table\TemplatePlacement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TemplatePlacement::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class TemplatePlacementTest extends TestCase
{
    public function testKeepsTheTemplateAndItsPosition(): void
    {
        $template = new TableTemplate(new QualifiedName(['src']));
        $placement = new TemplatePlacement($template, 2);
        self::assertSame($template, $placement->template);
        self::assertSame(2, $placement->position);
    }

    public function testRejectsANegativePosition(): void
    {
        $this->expectException(InvalidStructure::class);
        new TemplatePlacement(new TableTemplate(new QualifiedName(['src'])), -1);
    }
}
