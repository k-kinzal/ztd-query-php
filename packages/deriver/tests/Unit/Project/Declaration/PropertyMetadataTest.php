<?php

declare(strict_types=1);

namespace Tests\Unit\Project\Declaration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class PropertyMetadataTest extends TestCase
{
    public function testMetadataDistinguishesAbsentAndNullDefaults(): void
    {
        $absent = new \Deriver\Project\Declaration\PropertyMetadata('x', 'C', 'mixed', 'public', false, false, null);
        $null = new \Deriver\Project\Declaration\PropertyMetadata('y', 'C', 'mixed', 'public', false, false, \Deriver\Value\Term::constant(null));
        self::assertNull($absent->default);
        self::assertNotNull($null->default);
        self::assertTrue($null->default->isConcrete());
    }
}
