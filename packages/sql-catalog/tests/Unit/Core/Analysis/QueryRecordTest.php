<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\QueryRecord;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

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

    public function testIsTruncatedOnlyWhenABoundCutTheSearchShort(): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');

        self::assertFalse((new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1')))->isTruncated());
        self::assertTrue((new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), truncated: true))->isTruncated());
    }

    #[DataProvider('providerIsTruncatedAfterAbsorb')]
    public function testIsTruncatedOnceEitherReadingWasCutShort(bool $held, bool $absorbed, bool $expected): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');
        $record = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), truncated: $held);

        $record->absorb(new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), truncated: $absorbed));

        self::assertSame($expected, $record->isTruncated());
    }

    /**
     * @return list<array{bool, bool, bool}>
     */
    public static function providerIsTruncatedAfterAbsorb(): array
    {
        return [
            [false, false, false],
            [true, false, true],
            [false, true, true],
            [true, true, true],
        ];
    }

    public function testIsBoundOnlyAfterSomethingWasBound(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT 1'));
        self::assertFalse($record->isBound());
        $record->bind([], []);
        self::assertTrue($record->isBound());
    }

    public function testIsBoundAfterASingleValueIsBound(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT :id'));

        $record->bindOne(':id', Domain::literal(1));

        self::assertTrue($record->isBound());
    }

    public function testIsBoundAfterAbsorbOnlyWhenEitherReadingWasBound(): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');
        $neither = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'));
        $neither->absorb(new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1')));
        $both = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'));
        $both->bind([], []);
        $other = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'));
        $other->bind([], []);
        $both->absorb($other);

        self::assertFalse($neither->isBound());
        self::assertTrue($both->isBound());
    }

    public function testBindOneWritesAPositionCountedFromOne(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT ?, ?'));
        $record->bind([Domain::literal('a'), Domain::literal('b')], []);

        $record->bindOne(1, Domain::literal('x'));

        self::assertSame(['x', 'b'], array_map(
            static fn (Domain $domain): string|int|float|bool|null => $domain->soleLiteral()?->value,
            $record->positional(),
        ));
    }

    public function testPositionalKeepsAValueAtThePositionItWasBoundTo(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 's'), 'k', TextPattern::fromText('SELECT ?, ?, ?'));

        $record->bindOne(3, Domain::literal('third'));
        $record->bindOne(1, Domain::literal('first'));

        self::assertSame([0, 2], array_keys($record->positional()));
        self::assertSame('third', $record->positional()[2]->soleLiteral()?->value);
    }

    public function testIsTruncatedIsFalseAndCombinedIsFalseUnlessToldOtherwise(): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');

        $fresh = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'));

        self::assertFalse($fresh->isTruncated());
        self::assertFalse($fresh->combined);
        self::assertTrue((new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), null, true))->combined);
    }
    #[DataProvider('providerIsTruncatedAfterAbsorb')]
    public function testAbsorbPreservesCombinationUncertaintyInEitherOrder(bool $held, bool $absorbed, bool $expected): void
    {
        $site = new CallSite('a.php', 1, 'f', 's');
        $record = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), combined: $held);
        $record->absorb(new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), combined: $absorbed));
        self::assertSame($expected, $record->combined);
    }

}
