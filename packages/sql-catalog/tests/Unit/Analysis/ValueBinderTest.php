<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Analysis\ValueBinder;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Extension\SinkCallKind;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ValueBinder::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class ValueBinderTest extends TestCase
{
    public function testOpenRecordsFindsTheStatementsAHandleStandsFor(): void
    {
        $recorder = new StatementRecorder();
        $record = $recorder->record(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'h', TextPattern::fromText('SELECT ?'));
        $recorder->filePrepared('h', [$record]);

        $binder = new ValueBinder($recorder);
        self::assertSame([$record], $binder->openRecords(Domain::of(new ObjectTerm('PDOStatement', null, 'h'))));
    }

    public function testOpenRecordsIsEmptyForAReceiverWithoutAHandle(): void
    {
        self::assertSame([], (new ValueBinder(new StatementRecorder()))->openRecords(Domain::literal('a')));
    }

    public function testBindValuesReachesEveryStatement(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.prepare');
        $first = new QueryRecord($site, 'h', TextPattern::fromText('SELECT ?'));
        $second = new QueryRecord($site, 'h', TextPattern::fromText('SELECT ?, ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDOStatement', 'execute', SinkRole::Execute, valuesParameter: 0);
        $values = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal(7))]));

        (new ValueBinder(new StatementRecorder()))->bindValues([$first, $second], $sink, [$values]);

        self::assertTrue($first->isBound());
        self::assertTrue($second->isBound());
    }

    public function testBindOneRecordReadsAnArrayOfValues(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDOStatement', 'execute', SinkRole::Execute, valuesParameter: 0);
        $values = Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::literal(7)),
            new ArrayEntry(Domain::literal(':id'), Domain::literal(9)),
        ]));

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [$values]);

        self::assertSame(7, $record->positional()[0]->soleLiteral()?->value);
        self::assertSame(9, $record->named()['id']->soleLiteral()?->value);
    }

    public function testBindOneRecordReadsVariadicValues(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'mysqli_stmt', 'bind_param', SinkRole::Bind, valuesFrom: 1);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::literal('s'), Domain::literal('a')]);

        self::assertSame('a', $record->positional()[0]->soleLiteral()?->value);
    }

    public function testBindOneRecordReadsOneNamedValue(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT :id'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDOStatement', 'bindValue', SinkRole::Bind, nameParameter: 0, valueParameter: 1);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::literal(':id'), Domain::literal(9)]);

        self::assertSame(9, $record->named()['id']->soleLiteral()?->value);
    }

    public function testBindOneRecordDoesNothingWhenTheCallBindsNothing(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT 1'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDO', 'query', SinkRole::Query, sqlParameter: 0);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::literal('SELECT 1')]);

        self::assertFalse($record->isBound());
    }

    public function testBindOneRecordIgnoresAValuesArgumentThatDidNotResolve(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDOStatement', 'execute', SinkRole::Execute, valuesParameter: 0);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::unknown()]);

        self::assertFalse($record->isBound());
    }

    public function testBindOneRecordLeavesAnArrayOfValuesThatDidNotResolveUnboundEvenWhenValuesCouldBeListed(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'Db', 'run', SinkRole::Execute, valuesParameter: 0, valuesFrom: 1);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::unknown(), Domain::literal('a')]);

        self::assertFalse($record->isBound());
    }

    public function testBindOneRecordReadsVariadicValuesWithoutAlsoReadingOneNamedValue(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'Db', 'run', SinkRole::Bind, valuesFrom: 1, nameParameter: 0, valueParameter: 1);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::literal(':id'), Domain::literal('a')]);

        self::assertSame('a', $record->positional()[0]->soleLiteral()?->value);
        self::assertSame([], $record->named());
    }

    public function testBindOneRecordNeedsBothTheNameAndTheValueOfASingleBind(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT :id'));
        $nameOnly = new SinkSpec('s', SinkCallKind::Method, 'Db', 'name', SinkRole::Bind, nameParameter: 0);
        $valueOnly = new SinkSpec('s', SinkCallKind::Method, 'Db', 'value', SinkRole::Bind, valueParameter: 1);
        $binder = new ValueBinder(new StatementRecorder());

        $binder->bindOneRecord($record, $nameOnly, [Domain::literal(':id'), Domain::literal(9)]);
        $binder->bindOneRecord($record, $valueOnly, [Domain::literal(':id'), Domain::literal(9)]);

        self::assertFalse($record->isBound());
    }

    public function testBindOneRecordAppendsAValueWhoseNameDidNotResolve(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'h', TextPattern::fromText('SELECT ?'));
        $sink = new SinkSpec('s', SinkCallKind::Method, 'PDOStatement', 'bindValue', SinkRole::Bind, nameParameter: 0, valueParameter: 1);

        (new ValueBinder(new StatementRecorder()))->bindOneRecord($record, $sink, [Domain::unknown(), Domain::literal(9)]);

        self::assertSame(9, $record->positional()[0]->soleLiteral()?->value);
        self::assertSame([], $record->named());
    }

    public function testBindingKeyReadsOnlyScalarKeys(): void
    {
        $binder = new ValueBinder(new StatementRecorder());
        self::assertSame(1, $binder->bindingKey(1));
        self::assertSame(':id', $binder->bindingKey(':id'));
        self::assertNull($binder->bindingKey(true));
        self::assertNull($binder->bindingKey(null));
    }
}
