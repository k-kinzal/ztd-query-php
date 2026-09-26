<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\TestReference;

#[CoversClass(TestReference::class)]
#[UsesClass(Fields::class)]
#[Small]
final class TestReferenceTest extends TestCase
{
    public function testFromReadsRunnerAndTarget(): void
    {
        $reference = TestReference::from(['runner' => 'unit', 'target' => 'Sample\PassingTest::testPass']);
        self::assertSame('unit', $reference->runner);
        self::assertSame('auto', $reference->run);
        self::assertSame('Sample\PassingTest::testPass', $reference->target);
    }

    #[DataProvider('providerFromRejectsInvalidEntries')]
    public function testFromRejectsInvalidEntries(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        TestReference::from($value);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerFromRejectsInvalidEntries(): array
    {
        return [
            'not a mapping' => ['unit', 'test must be a mapping.'],
            'unknown field' => [['runner' => 'unit', 'target' => 't', 'name' => 'n'], "test: unknown field 'name'."],
            'unknown policy' => [['runner' => 'unit', 'target' => 't', 'run' => 'never'], 'Test run must be auto or manual.'],
            'boolean policy' => [['runner' => 'unit', 'target' => 't', 'run' => false], 'run must be a nonempty string.'],
            'missing runner' => [['target' => 't'], 'runner must be a nonempty string.'],
            'missing target' => [['runner' => 'unit'], 'target must be a nonempty string.'],
        ];
    }
    public function testFromRetainsManualExecutionPolicy(): void
    {
        self::assertSame('manual', TestReference::from(['runner' => 'unit', 'target' => 'slow', 'run' => 'manual'])->run);
    }
}
