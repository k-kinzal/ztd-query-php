<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Declaration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class PropertyMetadataTest extends TestCase
{
    public function testMetadataDistinguishesAbsentAndNullDefaults(): void
    {
        $absent = new \Deriver\Deriver\Evaluator\Declaration\PropertyMetadata('x', 'C', 'mixed', \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Public, false, false, null);
        $null = new \Deriver\Deriver\Evaluator\Declaration\PropertyMetadata('y', 'C', 'mixed', \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Public, false, false, \Deriver\Deriver\Evaluator\Term::constant(null));
        self::assertNull($absent->default);
        self::assertNotNull($null->default);
        self::assertTrue($null->default->isConcrete());
    }
}
