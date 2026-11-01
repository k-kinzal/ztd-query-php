<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TemporalLiteral::class)]
#[Medium]
final class TemporalLiteralTest extends TestCase
{
    public function testTypeKeepsModeDependentCalendarValues(): void
    {
        self::assertSame('2024-02-31 03:04:05', (new TemporalLiteral(TemporalForm::Timestamp, '2024-02-31 03:04:05'))->text);
        self::assertSame('0000-00-00 00:00:00', (new TemporalLiteral(TemporalForm::Timestamp, '0000-00-00 00:00:00'))->text);
    }

    public function testTypeAnswersDateWithoutAPrecision(): void
    {
        $type = (new TemporalLiteral(TemporalForm::Date, '2024-01-02'))->type();

        self::assertSame(TemporalKind::Date, $type->kind);
        self::assertNull($type->precision);
    }

    public function testTypeCountsTheFractionalDigitsUpToSix(): void
    {
        self::assertNull((new TemporalLiteral(TemporalForm::Time, '10:11:12'))->type()->precision);
        self::assertNull((new TemporalLiteral(TemporalForm::Time, '10:11:12.'))->type()->precision);
        self::assertSame('1', (new TemporalLiteral(TemporalForm::Time, '10:11:12.5'))->type()->precision);
        self::assertSame('6', (new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 03:04:05.123456789'))->type()->precision);
        self::assertNull((new TemporalLiteral(TemporalForm::Timestamp, '2024.01.02.03.04.05'))->type()->precision);
        self::assertNull((new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 03:04.5'))->type()->precision);
    }

    public function testTypeAnswersDateTimeForTimestampAndIgnoresATimeZoneOffset(): void
    {
        $type = (new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 03:04:05.12+05:30'))->type();

        self::assertSame(TemporalKind::DateTime, $type->kind);
        self::assertSame('2', $type->precision);
        self::assertSame(TemporalKind::Time, (new TemporalLiteral(TemporalForm::Time, '10:11:12.123'))->type()->kind);
        self::assertSame('3', (new TemporalLiteral(TemporalForm::Time, '10:11:12.123'))->type()->precision);
    }

    public function testDeriveScalarAnswersTheTemporalTypeThatIsNeverNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT a FROM t WHERE a = TIMESTAMP '2024-01-02 03:04:05.25'");
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(TemporalLiteral::class, $literal);
        $fact = $literal->scalarInformation();

        self::assertSame(TemporalForm::Timestamp, $literal->form);
        self::assertSame('2024-01-02 03:04:05.25', $literal->text);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Domain::class, $fact->type->descriptor);
        self::assertSame(['DATETIME', 22, 2], [$fact->type->descriptor->name(), $fact->type->descriptor->length, $fact->type->descriptor->decimals]);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarAnswersDateForADateLiteral(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT DATE '2024-01-02'");
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $item->expression->scalarInformation();

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Domain::class, $fact->type->descriptor);
        self::assertSame(['DATE', 10], [$fact->type->descriptor->name(), $fact->type->descriptor->length]);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testDeriveScalarKeepsDecodedTextAcrossLexicalProfiles(): void
    {
        $literal = new TemporalLiteral(TemporalForm::Date, '2024-01-02');
        $statement = new Select([], [new SelectExpression($literal)]);
        $plain = new Semantics(Dialect::MySql);
        $verbatim = new Semantics(Dialect::MySql, null, \SqlSemantics\Platform\MySql\Mode::fromString('NO_BACKSLASH_ESCAPES'));

        $first = Semantics::fromStatement($plain->context(), new \SqlSemantics\Platform\MySql\Statement\QueryRoot($statement));
        $second = Semantics::fromStatement($verbatim->context(), new \SqlSemantics\Platform\MySql\Statement\QueryRoot($statement));
        self::assertEquals($first->field(0)->type, $second->field(0)->type);
        self::assertSame($first->field(0)->nullability, $second->field(0)->nullability);
        self::assertSame("SELECT DATE '2024-01-02'", $first->toString());
        self::assertSame($first->toString(), $second->toString());
        self::assertFalse($literal->hasBinding());
    }

    public function testRenderWritesTheKeywordAndTheQuotedText(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame("SELECT DATE '2024-01-02' AS v", $semantics->analyze("select date '2024-01-02' as v")->toString());
        self::assertSame('SELECT TIME "01:02:03" AS v', $semantics->analyze('SELECT TIME "01:02:03" AS v')->toString());
        self::assertSame('SELECT TIMESTAMP "2024-01-02 03:04:05" AS v', $semantics->analyze('SELECT TIMESTAMP "2024-01-02 03:04:05" AS v')->toString());
        self::assertSame("SELECT date '2024-01-02'", $semantics->analyze("SELECT date '2024-01-02'")->toString());
    }

    #[DataProvider('providerInvalidOperands')]
    public function testValidRetainsInvalidOperands(TemporalForm $form, string $text): void
    {
        $literal = new TemporalLiteral($form, $text);
        self::assertFalse($literal->valid());
        self::assertSame($text, $literal->text);
    }

    /**
     * @return iterable<string, array{TemporalForm, string}>
     */
    public static function providerInvalidOperands(): iterable
    {
        yield 'invalid date' => [TemporalForm::Date, 'bad'];
        yield 'invalid time' => [TemporalForm::Time, '839:00:00'];
        yield 'invalid timestamp' => [TemporalForm::Timestamp, '2024-01-02'];
    }

    #[DataProvider('providerInvalidOperands')]
    public function testDeriveScalarPublishesAConditionBesideTheCompleteValue(TemporalForm $form, string $text): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statement = Semantics::fromStatement($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new Select([], [new SelectExpression(new TemporalLiteral($form, $text))])));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral($text, $form);
        self::assertEquals([$problem], $statement->diagnostics());
        self::assertEquals(new \SqlSemantics\Statement\Type\Invalid($problem), $statement->field(0)->type);
        self::assertSame('SELECT ' . $form->value . " '" . $text . "'", $statement->toString());
        $notice = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notice);
        self::assertEquals($problem, $notice->problem);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization, $notice->phase);
        self::assertTrue($notice->aborts);
    }
}
