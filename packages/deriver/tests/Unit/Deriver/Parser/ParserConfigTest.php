<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Parser;

use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserConfig::class)]
#[Small]
final class ParserConfigTest extends TestCase
{
    public function testRetainsOnlyThePhpVersion(): void
    {
        self::assertSame(['phpVersion' => '8.4'], get_object_vars(new ParserConfig('8.4')));
    }

    #[DataProvider('providerInvalidVersions')]
    public function testRejectsMalformedVersionStrings(string $version): void
    {
        $this->expectException(InvalidInputException::class);
        new ParserConfig($version);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidVersions(): iterable
    {
        yield 'empty' => [''];
        yield 'major only' => ['8'];
        yield 'trailing text' => ['8.3-invalid'];
        yield 'patch version' => ['8.3.1'];
    }
}
