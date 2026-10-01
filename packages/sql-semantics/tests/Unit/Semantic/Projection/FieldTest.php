<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Projection;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Projection\Field;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(Field::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FieldTest extends TestCase
{
    public function testToStringKeepsAliasSeparateFromColumnOwnership(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo AS label FROM bar');
        $field = $statement->field('label');
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $field->expression);
        self::assertSame('foo', $field->expression->name->value);
        self::assertSame('foo AS label', $field->toString());
    }
}
