<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Projection;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Projection\Field;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Projection\Fields::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FieldsTest extends TestCase
{
    public function testAddFieldPreservesOriginalAndBindsTheNewColumn(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        $fields = $statement->fields()->addField(new Field($statement->scope->column(new Name('label'))));
        self::assertCount(1, $statement->fields()->items);
        self::assertSame('text', $fields->field('label')->type->name);
        self::assertSame($statement->field('foo'), $fields->field('foo'));
    }

    public function testFieldRejectsDuplicateResultNames(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo, foo FROM bar');
        $this->expectException(OutOfBoundsException::class);
        $statement->field('foo');
    }

    public function testOutputsAndToStringPreserveProjectionOrder(): void
    {
        $fields = SemanticCases::select(Dialect::Sqlite, 'SELECT label, foo FROM bar')->fields();
        self::assertSame(['label', 'foo'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->outputs()));
        self::assertSame('label, foo', $fields->toString());
    }
    public function testToStringRetainsAllProjectionPositions(): void
    {
        self::assertSame('foo, foo', SemanticCases::select(Dialect::Sqlite, 'SELECT foo, foo FROM bar')->fields()->toString());
    }
}
