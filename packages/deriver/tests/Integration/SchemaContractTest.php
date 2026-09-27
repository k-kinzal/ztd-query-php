<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Fake\Analysis;
use Tests\Fake\ReportSchema;

#[CoversNothing]
#[Small]
final class SchemaContractTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSchemaAcceptsARealSymbolicReport(): void
    {
        $result = Analysis::returns('<?php function target($id){return "user:".$id;}');
        self::assertTrue(ReportSchema::accepts($result->toJson()));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSchemaRejectsAnUnknownQueryKind(): void
    {
        $report = json_decode(Analysis::returns('<?php function target(){return 1;}')->toJson(), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $report);
        self::assertInstanceOf(stdClass::class, $report->query);
        $report->query->kind = 'execute-host-code';
        self::assertFalse(ReportSchema::accepts(json_encode($report, JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSchemaRejectsNegativeLogicalWork(): void
    {
        $report = json_decode(Analysis::returns('<?php function target(){return 1;}')->toJson(), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $report);
        self::assertInstanceOf(stdClass::class, $report->statistics);
        $report->statistics->transfers = -1;
        self::assertFalse(ReportSchema::accepts(json_encode($report, JSON_THROW_ON_ERROR)));
    }

    /**
     * @param string $scalar Malformed tagged scalar
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerMalformedScalars')]
    public function testSchemaRejectsMalformedScalars(string $scalar): void
    {
        $report = json_decode(Analysis::returns('<?php function target(){return 1;}')->toJson(), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $report);
        self::assertInstanceOf(stdClass::class, $report->values);
        self::assertInstanceOf(stdClass::class, $report->values->v0);
        $report->values->v0->literal = json_decode($scalar, false, 512, JSON_THROW_ON_ERROR);
        self::assertFalse(ReportSchema::accepts(json_encode($report, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, array{string}> Invalid scalar records independent of the encoder
     */
    public static function providerMalformedScalars(): array
    {
        return [
            'integer stored as a JSON number' => ['{"type":"int64","value":1}'],
            'integer with a leading zero' => ['{"type":"int64","value":"01"}'],
            'float with missing bytes' => ['{"type":"float64","value":"00"}'],
            'float with nonhex digits' => ['{"type":"float64","value":"zzzzzzzzzzzzzzzz"}'],
            'non-Base64 byte string' => ['{"type":"bytes","value":"%%%%"}'],
            'Boolean stored as text' => ['{"type":"bool","value":"true"}'],
            'non-null null payload' => ['{"type":"null","value":false}'],
        ];
    }
}
