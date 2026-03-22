<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\RewriteStep;

#[CoversClass(RewriteStep::class)]
final class RewriteStepTest extends TestCase
{
    public function testConstructsAReadonlyRewriteStep(): void
    {
        $step = new RewriteStep('canonicalize.ident', 'Canonicalize identifier entry points.');

        self::assertSame('canonicalize.ident', $step->id);
        self::assertSame('Canonicalize identifier entry points.', $step->description);
    }

    public function testRejectsAnEmptyIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rewrite step id must be a non-empty string.');

        new RewriteStep('', 'description');
    }

    public function testRejectsAnEmptyDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rewrite step description must be a non-empty string.');

        new RewriteStep('step', '');
    }
}
