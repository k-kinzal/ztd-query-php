<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Metadata;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class PropertyMetadataTest extends TestCase
{
    public function testMetadataDistinguishesAbsentAndNullDefaults(): void
    {
        $absent = new \Deriver\Model\Metadata\PropertyMetadata('x', 'C', 'mixed', 'public', false, false, null);
        $null = new \Deriver\Model\Metadata\PropertyMetadata('y', 'C', 'mixed', 'public', false, false, \Deriver\Value\Term::constant(null));
        self::assertNull($absent->default);
        self::assertNotNull($null->default);
        self::assertTrue($null->default->isConcrete());
    }
}
