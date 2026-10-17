<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use Deriver\Project\Configuration;
use Deriver\Reference\ResultRef;
use Deriver\Result\CandidateCollection;
use Deriver\Value\Term;
use JsonException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class CandidateCollectionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOffsetGetReturnsTheSameCandidatesAsIteration(): void
    {
        $collection = A::returns('function target($x){return $x?35:65;}');
        self::assertSame(35, $collection[0]->term->native());
        self::assertSame(65, $collection[1]->term->native());
        self::assertSame([$collection[0], $collection[1]], iterator_to_array($collection));
        self::assertArrayNotHasKey('candidates', get_object_vars($collection));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerMissingOffsets')]
    public function testOffsetGetRejectsMissingAndNonIntegerOffsets(int|string|null $offset): void
    {
        $collection = A::returns();
        self::assertFalse($collection->offsetExists($offset));
        $this->expectException(OutOfBoundsException::class);
        $collection->offsetGet($offset);
    }

    /**
     * @return iterable<string, array{int|string|null}>
     */
    public static function providerMissingOffsets(): iterable
    {
        yield 'negative' => [-1];
        yield 'past the end' => [1];
        yield 'numeric string' => ['0'];
        yield 'string' => ['missing'];
        yield 'null' => [null];
    }

    public function testOffsetGetRejectsAnEmptyCollection(): void
    {
        $collection = new CandidateCollection([], new ResultRef('empty'));
        self::assertCount(0, $collection);
        self::assertFalse(isset($collection[0]));
        self::assertNull($collection[0] ?? null);
        $this->expectException(OutOfBoundsException::class);
        $collection[0];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOffsetExistsIncludesCandidatesWithNullValues(): void
    {
        $collection = A::returns('function target(){return null;}');
        $candidate = $collection[0] ?? null;
        self::assertNotNull($candidate);
        self::assertSame($collection[0], $candidate);
        self::assertTrue(isset($collection[0]));
        self::assertNull($candidate->result);
        self::assertFalse(isset($collection[1]));
        self::assertNull($collection[1] ?? null);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOffsetSetRejectsReplacement(): void
    {
        $collection = A::returns();
        $original = $collection[0];
        $replacement = A::returns('function target(){return 7;}')[0];
        $this->expectException(RuntimeException::class);
        try {
            $collection[0] = $replacement;
        } finally {
            self::assertCount(1, $collection);
            self::assertSame($original, $collection[0]);
        }
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOffsetSetRejectsAppend(): void
    {
        $collection = A::returns();
        $this->expectException(RuntimeException::class);
        try {
            $collection[] = $collection[0];
        } finally {
            self::assertCount(1, $collection);
            self::assertFalse(isset($collection[1]));
        }
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOffsetUnsetRejectsRemoval(): void
    {
        $collection = A::returns();
        $original = $collection[0];
        $this->expectException(RuntimeException::class);
        try {
            unset($collection[0]);
        } finally {
            self::assertCount(1, $collection);
            self::assertSame($original, $collection[0]);
        }
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testGetIteratorCannotChangeTheCollection(): void
    {
        $collection = A::returns();
        $original = $collection[0];
        $iterator = $collection->getIterator();
        unset($iterator[0]);
        self::assertCount(1, $collection);
        self::assertSame($original, $collection[0]);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCountIncludesPartialCandidates(): void
    {
        self::assertSame(1, A::returns('function target($x){return $x;}')->count());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testGetIteratorReturnsCandidateRecords(): void
    {
        $candidate = A::returns()->getIterator()[0];
        self::assertNotNull($candidate);
        self::assertSame(\Deriver\Result\CandidateStatus::Analyzed, $candidate->type);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testForCallerRetainsSameContextAmbiguity(): void
    {
        $result = A::returns('function target($x){return $x?35:65;}function caller($x){return target($x);}');
        self::assertCount(2, $result->forCaller('caller'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testJsonSerializeUsesOnlyCandidateFields(): void
    {
        self::assertSame(['type','type_name','result','evidence'], array_keys(get_object_vars(A::returns()->jsonSerialize()[0])));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testToJsonRedactsSecretValues(): void
    {
        $rule = \Deriver\Project\Expansion\Rule::constantFunction('secret', '1', 'load', Term::constant('private-value', true));
        $result = A::returns('function target(){return load();}', new Configuration(expansionRules:[$rule]));
        self::assertStringNotContainsString(base64_encode('private-value'), $result->toJson());
    }

}
