<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Render\Record;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

#[CoversClass(Record::class)]
#[UsesClass(Fields::class)]
#[Small]
final class RecordTest extends TestCase
{
    public function testFieldsReturnsTheRecordFields(): void
    {
        self::assertSame(['runner' => 'unit', 'target' => 'A::b'], Record::fields((object) ['runner' => 'unit', 'target' => 'A::b']));
    }

    public function testFieldsReturnsNothingForAnEmptyRecord(): void
    {
        self::assertSame([], Record::fields(new stdClass()));
    }

    #[DataProvider('providerNotRecords')]
    public function testFieldsRejectsAValueThatIsNotARecord(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected a Markdown record.');
        Record::fields($value);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerNotRecords(): array
    {
        return [
            'string' => ['REQ-001'],
            'mapping array' => [['runner' => 'unit']],
            'null' => [null],
        ];
    }

    public function testFieldsRejectsNumericKeys(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('record must have string keys.');
        Record::fields((object) ['first']);
    }
}
