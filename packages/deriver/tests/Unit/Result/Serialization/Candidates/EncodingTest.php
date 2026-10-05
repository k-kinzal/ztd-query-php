<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Serialization\Candidates;

use Deriver\Result\Serialization\Candidates\Encoding as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class EncodingTest extends TestCase
{
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
