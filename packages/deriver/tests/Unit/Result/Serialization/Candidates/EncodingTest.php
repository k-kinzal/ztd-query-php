<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Serialization\Candidates;

use Deriver\Result\CandidateStatus;
use Deriver\Result\Serialization\Candidates\Encoding as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class EncodingTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerStatuses')]
    public function testEncodeExposesTheWireStringForEachEnumStatus(string $source, CandidateStatus $status, string $wire): void
    {
        $result = A::returns($source);
        self::assertSame($status, $result->candidates[0]->type);
        self::assertSame($wire, (new Subject())->encode($result)[0]->type);
        self::assertSame($wire, $result->jsonSerialize()[0]->type);
        $json = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertIsArray($json[0]);
        self::assertSame($wire, $json[0]['type']);
    }

    /**
     * @return iterable<string, array{string, CandidateStatus, string}>
     */
    public static function providerStatuses(): iterable
    {
        yield 'concrete' => ['function target(){return 42;}', CandidateStatus::Analyzed, 'analyzed'];
        yield 'partial' => ['function target($x){return $x;}', CandidateStatus::Partials, 'partials'];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEncodePreservesBinaryAndLargeIntegerValues(): void
    {
        $result = A::returns('function target(){return ["\\xff",9223372036854775807];}');
        $records = (new Subject())->encode($result);
        self::assertSame('array', $records[0]->type_name);
        self::assertIsArray($records[0]->result);
    }

}
