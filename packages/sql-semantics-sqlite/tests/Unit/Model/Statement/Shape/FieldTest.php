<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Choice;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Field::class)]
#[Medium]
final class FieldTest extends TestCase
{
    public function testColumnReachesTheDeclarationADirectReferenceReturns(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')->declarations()[0];

        $field = $semantics->inspect('SELECT a AS x FROM t', [$table])->field('x');

        self::assertSame(0, $field->position);
        self::assertSame('x', $field->name?->value);
        self::assertSame($table->columns[0], $field->column());
        self::assertSame($field->slot->type, $field->type);
        self::assertSame(Nullability::NotNull, $field->nullability);
        self::assertInstanceOf(ColumnUse::class, $field->expression);
        self::assertInstanceOf(ResolvedColumn::class, $field->resolution);
    }

    public function testColumnIsNullForAComputedField(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $field = $semantics->inspect('SELECT a + 1 FROM t', [$table])->field(0);

        self::assertNull($field->column());
        self::assertNull($field->resolution);
        self::assertSame('a + 1', $field->name?->value);
        self::assertNotNull($field->expression);
    }

    public function testColumnFollowsASetOperationFieldWithoutASingleExpression(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $field = $semantics->inspect('SELECT a FROM t UNION SELECT 1', [$table])->field(0);

        self::assertNull($field->expression);
        self::assertSame('a', $field->name?->value);
        self::assertInstanceOf(Choice::class, $field->type);
    }
}
