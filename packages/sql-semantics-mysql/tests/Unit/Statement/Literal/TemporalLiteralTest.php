<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TemporalLiteral::class)]
#[Medium]
final class TemporalLiteralTest extends TestCase
{
    public function testTypeAnswersDateWithoutAPrecision(): void
    {
        $type = (new TemporalLiteral(TemporalForm::Date, '2024-01-02 03:04:05.123'))->type();

        self::assertSame(TemporalKind::Date, $type->kind);
        self::assertNull($type->precision);
    }

    public function testTypeCountsTheFractionalDigitsUpToSix(): void
    {
        self::assertNull((new TemporalLiteral(TemporalForm::Time, '10:11:12'))->type()->precision);
        self::assertNull((new TemporalLiteral(TemporalForm::Time, '10:11:12.'))->type()->precision);
        self::assertSame('1', (new TemporalLiteral(TemporalForm::Time, '10:11:12.5'))->type()->precision);
        self::assertSame('6', (new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 03:04:05.123456789'))->type()->precision);
    }

    public function testTypeAnswersDateTimeForTimestampAndIgnoresATimeZoneOffset(): void
    {
        $type = (new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 03:04:05.12+05:30'))->type();

        self::assertSame(TemporalKind::DateTime, $type->kind);
        self::assertSame('2', $type->precision);
        self::assertSame(TemporalKind::Time, (new TemporalLiteral(TemporalForm::Time, '10:11:12.123-01:00'))->type()->kind);
        self::assertSame('3', (new TemporalLiteral(TemporalForm::Time, '10:11:12.123-01:00'))->type()->precision);
    }

    public function testDeriveScalarAnswersTheTemporalTypeThatIsNeverNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT a FROM t WHERE a = TIMESTAMP '2024-01-02 03:04:05.25'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(TemporalLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame(TemporalForm::Timestamp, $literal->form);
        self::assertSame('2024-01-02 03:04:05.25', $literal->text);
        self::assertSame(EscapeRule::Backslash, $literal->escapes);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Temporal::class, $fact->type->descriptor);
        self::assertSame(TemporalKind::DateTime, $fact->type->descriptor->kind);
        self::assertSame('2', $fact->type->descriptor->precision);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarAnswersDateForADateLiteral(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT DATE '2024-01-02'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Temporal::class, $fact->type->descriptor);
        self::assertSame(TemporalKind::Date, $fact->type->descriptor->kind);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testDeriveScalarRejectsALiteralSpelledUnderTheOtherEscapeRule(): void
    {
        $context = (new Semantics(Dialect::MySql))->context();
        $statement = new Select([], [new SelectExpression(new TemporalLiteral(TemporalForm::Date, '2024-01-02', EscapeRule::Verbatim))]);

        $this->expectExceptionMessage('A temporal literal must be spelled under the escape rule of the language profile.');

        new Operation($context, $statement);
    }

    public function testRenderWritesTheKeywordAndTheQuotedText(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame("SELECT DATE '2024-01-02' AS v", $semantics->analyze("select date '2024-01-02' as v")->toString());
        self::assertSame("SELECT TIME 'a''b' AS v", $semantics->analyze("SELECT TIME 'a\\'b' AS v")->toString());
        self::assertSame("SELECT TIMESTAMP '2024-01-02 03:04:05' AS v", $semantics->analyze('SELECT TIMESTAMP "2024-01-02 03:04:05" AS v')->toString());
        self::assertSame("SELECT date '2024-01-02'", $semantics->analyze("SELECT date '2024-01-02'")->toString());
    }
}
