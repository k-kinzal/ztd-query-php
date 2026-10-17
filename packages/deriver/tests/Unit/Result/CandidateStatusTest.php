<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use Deriver\Result\CandidateStatus;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CandidateStatus::class)]
final class CandidateStatusTest extends TestCase
{
    /**
     * @throws JsonException If the published schema is not valid JSON
     */
    public function testStatusValuesMatchThePublishedCandidateSchema(): void
    {
        $bytes = file_get_contents(__DIR__ . '/../../../resources/schema/candidates-v2.json');
        self::assertIsString($bytes);
        $schema = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $schema);
        self::assertInstanceOf(stdClass::class, $schema->items);
        self::assertInstanceOf(stdClass::class, $schema->items->properties);
        self::assertInstanceOf(stdClass::class, $schema->items->properties->type);
        self::assertSame($schema->items->properties->type->enum, array_column(CandidateStatus::cases(), 'value'));
    }
}
