<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\CoverageThresholds;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;

#[CoversClass(CoverageThresholds::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Source::class)]
#[Small]
final class CoverageThresholdsTest extends TestCase
{
    public function testReadReturnsThresholdsAsFloats(): void
    {
        $sources = ['manual' => new Source('manual', 'source.html', 'html', 'main p'), 'rfc' => new Source('rfc', 'rfc.xml', 'xml', 't')];
        self::assertSame(['manual' => 80.0, 'rfc' => 12.5], (new CoverageThresholds())->read(['manual' => 80, 'rfc' => 12.5], $sources));
    }

    public function testReadAcceptsNoThresholds(): void
    {
        self::assertSame([], (new CoverageThresholds())->read([], ['manual' => new Source('manual', 'source.html', 'html', 'main p')]));
    }

    public function testReadAcceptsBounds(): void
    {
        $sources = ['low' => new Source('low', 'source.html', 'html', 'p'), 'high' => new Source('high', 'source.html', 'html', 'p')];
        self::assertSame(['low' => 0.0, 'high' => 100.0], (new CoverageThresholds())->read(['low' => 0, 'high' => 100], $sources));
    }

    #[DataProvider('providerInvalid')]
    public function testReadRejectsInvalidThresholds(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new CoverageThresholds())->read($value, ['manual' => new Source('manual', 'source.html', 'html', 'main p')]);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerInvalid(): array
    {
        return [
            'unknown source' => [['manual' => 50, 'other' => 50], 'Unknown source threshold: other'],
            'above one hundred' => [['manual' => 100.5], 'coverage.sources.manual must be a number from 0 to 100.'],
            'negative' => [['manual' => -1], 'coverage.sources.manual must be a number from 0 to 100.'],
            'not a number' => [['manual' => '50'], 'coverage.sources.manual must be a number from 0 to 100.'],
            'not a mapping' => ['manual', 'coverage.sources must be a mapping.'],
            'list' => [[50], 'coverage.sources must have string keys.'],
        ];
    }
}
