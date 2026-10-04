<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ValuesClause::class)]
#[Medium]
final class ValuesClauseTest extends TestCase
{
    public function testDeriveStatementNamesTheColumnsByPosition(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("VALUES (1, 'a', NULL)");

        self::assertSame(['column1', 'column2', 'column3'], array_map(static fn (object $field): ?string => $field->name?->value, [...$query->fields() ?? []]));
        self::assertSame([], $query->facts->diagnostics);
        self::assertInstanceOf(ValuesClause::class, $query->statement);
        self::assertSame($query->statement->rows[0]->values[1], $query->field(1)->expression);
    }

    public function testDeriveQueryChoosesTheTypeOverTheRowsAndTheirNullability(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("VALUES (1, 'a'), ('x', NULL)");
        $first = $query->field(0)->type;
        $second = $query->field(1)->type;

        self::assertInstanceOf(Choice::class, $first);
        self::assertSame([Storage::Integer, Storage::Text], $first->alternatives);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $second);
        self::assertSame(Storage::Text, $second->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertNull($query->field(0)->expression);
    }

    public function testDeriveQueryReportsARowOfAnotherWidthOnce(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('VALUES (1, 2), (3), (4, 5, 6)');

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $query->facts->diagnostics[0]);
        self::assertSame(ArityRule::ValueRows, $query->facts->diagnostics[0]->rule);
        self::assertSame('All VALUES must have the same number of terms: 2 and 1.', $query->facts->diagnostics[0]->message());
        self::assertCount(2, $query->fields() ?? []);
    }

    public function testDeriveQuerySeesNoRelationOfItsOwn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('VALUES (a)');

        self::assertInstanceOf(ValuesClause::class, $query->statement);
        self::assertInstanceOf(MissingColumn::class, $query->facts->scalar($query->statement->rows[0]->values[0])->resolution);
        self::assertSame('Column a does not exist.', $query->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('VALUES (RAISE(IGNORE))');

        self::assertSame(MisuseRule::RaiseOutsideTrigger->value, $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheRows(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('values(1),(2)');

        self::assertSame('VALUES (1), (2)', $query->toString());
    }

    public function testRenderRefusesAClauseWithoutRows(): void
    {
        $this->expectExceptionMessage('A VALUES clause has at least one row.');

        new ValuesClause([]);
    }
}
