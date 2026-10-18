<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\BoundArgument;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\BoundArgument
 */
#[CoversClass(BoundArgument::class)]
#[UsesClass(Term::class)]
#[Small]
final class BoundArgumentTest extends TestCase
{
    public function testMetadataDistinguishesOmittedAndEvaluatedValues(): void
    {
        $binding = new BoundArgument('name', Term::constant('default'), supplied: false);
        self::assertFalse($binding->supplied);
        self::assertSame('default', $binding->value->native());
        self::assertNull($binding->location);
    }
}
