<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(QueryRecord::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class QueryRecordTest extends TestCase
{
    public function testBindKeepsValuesByPositionAndByName(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bind([Domain::literal(1)], [':id' => Domain::literal(2)]);
        self::assertSame(1, $record->positional()[0]->soleLiteral()?->value);
        self::assertSame(2, $record->named()['id']->soleLiteral()?->value);
    }

    public function testBindOneWritesUnderThePositionOrTheName(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bindOne(1, Domain::literal('first'));
        $record->bindOne(':name', Domain::literal('Grace'));
        $record->bindOne(null, Domain::literal('appended'));
        self::assertSame('first', $record->positional()[0]->soleLiteral()?->value);
        self::assertSame('Grace', $record->named()['name']->soleLiteral()?->value);
        self::assertCount(2, $record->positional());
    }

    public function testAbsorbTakesTheUnionOfBothReadings(): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');
        $first = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $first->bind([Domain::literal(1)], ['id' => Domain::literal('a')]);
        $second = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $second->bind([Domain::literal(2)], ['id' => Domain::literal('b'), 'other' => Domain::literal('c')]);
        $first->absorb($second);
        self::assertCount(2, $first->positional()[0]->terms);
        self::assertCount(2, $first->named()['id']->terms);
        self::assertSame('c', $first->named()['other']->soleLiteral()?->value);
    }

    public function testAbsorbTakesOverValuesTheOtherReadingAloneHas(): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');
        $first = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $second = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $second->bind([Domain::literal(2)], []);
        $first->absorb($second);
        self::assertSame(2, $first->positional()[0]->soleLiteral()?->value);
        self::assertTrue($first->isBound());
    }

    public function testPositionalIsOrderedByPosition(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT ?, ?'));
        $record->bindOne(2, Domain::literal('second'));
        $record->bindOne(1, Domain::literal('first'));
        self::assertSame(['first', 'second'], array_map(
            static fn (Domain $domain): string|int|float|bool|null => $domain->soleLiteral()?->value,
            $record->positional(),
        ));
    }

    public function testNamedIsEmptyUntilSomethingIsBoundByName(): void
    {
        self::assertSame([], (new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT 1')))->named());
    }

    public function testIsBoundOnlyAfterSomethingWasBound(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT 1'));
        self::assertFalse($record->isBound());
        $record->bind([], []);
        self::assertTrue($record->isBound());
    }
}
