<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValuesQuery::class)]
#[Medium]
final class ValuesQueryTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsAsTheOutput(): void
    {
        $semantics = new Inspection();
        $values = new ValuesQuery([new ValueRow([new NumberLiteral('1')])]);
        $derivation = new Derivation($semantics->context());
        $derivation->statement($values);

        self::assertNotNull($derivation->facts()->output);
        self::assertSame('column_0', $derivation->facts()->output->fields()?->at(0)->name?->value);
    }

    public function testDeriveQueryNamesTheColumnsAndCombinesTheRows(): void
    {
        $semantics = new Inspection();
        $values = new ValuesQuery([new ValueRow([new NumberLiteral('1'), new NumberLiteral('2')]), new ValueRow([new NullLiteral(), new NumberLiteral('3')])]);
        $derivation = new Derivation($semantics->context());
        $fact = $derivation->query($values, $derivation->environment());

        self::assertSame(['column_0', 'column_1'], array_map(static fn (Field $field): ?string => $field->name?->value, $fact->fields()->items ?? []));
        self::assertNotNull($fact->fields());
        self::assertSame(Nullability::Nullable, $fact->fields()->at(0)->nullability);
        self::assertSame(Nullability::NotNull, $fact->fields()->at(1)->nullability);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testDeriveQueryReportsRowsOfDifferentLengths(): void
    {
        $semantics = new Inspection();
        $values = new ValuesQuery([new ValueRow([new NumberLiteral('1'), new NumberLiteral('2')]), new ValueRow([new NumberLiteral('3')])]);
        $derivation = new Derivation($semantics->context());
        $derivation->query($values, $derivation->environment());

        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $derivation->facts()->diagnostics[0]);
        self::assertSame(CountedList::ValueRows, $derivation->facts()->diagnostics[0]->list);
    }

    public function testRenderWritesTheRows(): void
    {
        $semantics = new Semantics();
        $values = new ValuesQuery([new ValueRow([new NumberLiteral('1')]), new ValueRow([new NumberLiteral('2')])]);
        $out = new Output(new Codec($semantics->profile()->grammar));
        $values->render($out);

        self::assertSame('VALUES ROW(1), ROW(2)', (new Lexical())->join($out->pieces()));
    }

    public function testAStatementWithoutRowsIsRejected(): void
    {
        $this->expectExceptionMessage('VALUES holds at least one row.');

        new ValuesQuery([]);
    }
}
