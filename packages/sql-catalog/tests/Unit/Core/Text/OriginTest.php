<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Text\Origin;

#[CoversClass(Origin::class)]
final class OriginTest extends TestCase
{
    public function testIsExternallyControlledOnlyForExternalInput(): void
    {
        self::assertTrue(Origin::External->isExternallyControlled());
        self::assertFalse(Origin::Parameter->isExternallyControlled());
        self::assertFalse(Origin::Unresolved->isExternallyControlled());
    }

    #[DataProvider('providerDescribe')]
    public function testDescribe(Origin $origin, string $expected): void
    {
        self::assertSame($expected, $origin->describe());
    }

    /**
     * @return list<array{Origin, string}>
     */
    public static function providerDescribe(): array
    {
        return [
            [Origin::External, 'external input'],
            [Origin::Parameter, 'a function parameter'],
            [Origin::Property, 'an object property'],
            [Origin::Call, 'a call the analyzer did not follow'],
            [Origin::Loop, 'a value built by a loop'],
            [Origin::Branch, 'values that differ between branches'],
            [Origin::Unresolved, 'an expression the analyzer could not resolve'],
        ];
    }
}
