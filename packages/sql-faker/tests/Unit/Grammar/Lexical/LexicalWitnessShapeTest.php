<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;

#[CoversClass(LexicalWitnessShape::class)]
#[UsesClass(LexicalCatalogException::class)]
final class LexicalWitnessShapeTest extends TestCase
{
    public function testOfReadsTheKeyedSpelling(): void
    {
        $witness = (new LexicalWitnessShape())->of('IDENT', [
            'id' => 'ident.bare',
            'sql' => 'name',
            'tokens' => ['IDENT'],
            'units' => ['identifier'],
        ]);

        self::assertSame('ident.bare', $witness['id']);
        self::assertSame('name', $witness['sql']);
        self::assertSame(['IDENT'], $witness['tokens']);
        self::assertSame(['identifier'], $witness['units']);
    }

    public function testOfReadsTheCompactSpelling(): void
    {
        $witness = (new LexicalWitnessShape())->of('IDENT', ['ident.bare', 'name', ['IDENT'], ['identifier']]);

        self::assertSame('ident.bare', $witness['id']);
        self::assertSame(['identifier'], $witness['units']);
    }

    public function testOfKeepsTheContextOfACompactWitness(): void
    {
        $witness = (new LexicalWitnessShape())
            ->of('IDENT', ['ident.bare', 'name', ['IDENT'], ['identifier'], 'SELECT %s']);

        self::assertSame('SELECT %s', $witness['context_sql'] ?? null);
    }

    public function testOfNamesTheTerminalOfAWitnessItCannotRead(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Invalid terminal witness: IDENT');

        (new LexicalWitnessShape())->of('IDENT', 'name');
    }

    public function testExpandedLeavesAKeyedWitnessAlone(): void
    {
        $keyed = ['id' => 'a', 'sql' => 'b', 'tokens' => [], 'units' => []];

        self::assertSame($keyed, (new LexicalWitnessShape())->expanded($keyed));
    }

    public function testExpandedLeavesAListOfTheWrongLengthAlone(): void
    {
        self::assertSame(['a', 'b'], (new LexicalWitnessShape())->expanded(['a', 'b']));
    }

    #[DataProvider('providerIncompleteWitness')]
    public function testDescribesAWitnessRejectsAnIncompleteEntry(mixed $witness): void
    {
        self::assertFalse((new LexicalWitnessShape())->describesAWitness($witness));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function providerIncompleteWitness(): iterable
    {
        yield 'not an array' => ['name'];
        yield 'missing id' => [['sql' => 'a', 'tokens' => [], 'units' => []]];
        yield 'id is not a string' => [['id' => 1, 'sql' => 'a', 'tokens' => [], 'units' => []]];
        yield 'sql is not a string' => [['id' => 'a', 'sql' => 1, 'tokens' => [], 'units' => []]];
        yield 'tokens is not a list' => [['id' => 'a', 'sql' => 'b', 'tokens' => [1 => 'x'], 'units' => []]];
        yield 'units hold a non-string' => [['id' => 'a', 'sql' => 'b', 'tokens' => [], 'units' => [1]]];
        yield 'context is not a string' => [
            ['id' => 'a', 'sql' => 'b', 'tokens' => [], 'units' => [], 'context_sql' => 1],
        ];
    }

    public function testDescribesAWitnessAcceptsTheMinimumAWitnessNeeds(): void
    {
        self::assertTrue((new LexicalWitnessShape())->describesAWitness([
            'id' => 'a',
            'sql' => 'b',
            'tokens' => [],
            'units' => [],
        ]));
    }

    public function testIsListOfStringsAcceptsTheEmptyList(): void
    {
        self::assertTrue((new LexicalWitnessShape())->isListOfStrings([]));
    }

    public function testIsListOfStringsRejectsAKeyedArray(): void
    {
        self::assertFalse((new LexicalWitnessShape())->isListOfStrings(['a' => 'b']));
    }

    public function testIsListOfStringsRejectsAListHoldingAnythingElse(): void
    {
        self::assertFalse((new LexicalWitnessShape())->isListOfStrings(['a', 1]));
    }
}
