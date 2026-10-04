<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(NullTestForm::class)]
#[Medium]
final class NullTestFormTest extends TestCase
{
    public function testEachFormIsReadFromItsSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a ISNULL, a NOTNULL, a NOT NULL FROM t');
        $forms = array_map(static fn (Field $field): ?NullTestForm => $field->expression instanceof NullTest ? $field->expression->form : null, $query->fields()->items ?? []);

        self::assertSame([NullTestForm::IsNull, NullTestForm::NotNull, NullTestForm::NotNullWords], $forms);
    }

    public function testEachFormIsADistinctCase(): void
    {
        self::assertCount(3, NullTestForm::cases());
        self::assertNotSame(NullTestForm::NotNull, NullTestForm::NotNullWords);
    }
}
