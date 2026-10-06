<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Candidates;

use Deriver\Project\Configuration;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class CandidateCollectionTest extends TestCase
{
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
        self::assertSame('analyzed', $candidate->type);
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
        $rule = \Deriver\Model\Expansion\Rule::constantFunction('secret', '1', 'load', Term::constant('private-value', true));
        $result = A::returns('function target(){return load();}', new Configuration(expansionRules:[$rule]));
        self::assertStringNotContainsString(base64_encode('private-value'), $result->toJson());
    }

}
