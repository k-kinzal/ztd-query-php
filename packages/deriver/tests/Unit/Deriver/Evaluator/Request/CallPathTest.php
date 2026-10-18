<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Request;

use Deriver\Deriver\Evaluator\Request\CallPath;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CallPathTest extends TestCase
{
    public function testCallPathRejectsAnEmptyPattern(): void
    {
        $this->expectException(InvalidInputException::class);
        new CallPath([]);
    }

    public function testAdvanceSeparatesExactAndArbitraryDepthEdges(): void
    {
        $path = new CallPath(['entry','...','bar']);
        self::assertSame([1], $path->advance('BAR', 2));
        self::assertSame([], $path->advance('other', 2));
        self::assertSame([-1,1], $path->advance('\\ENTRY', 1));
        self::assertSame([1], $path->advance('intermediate', 1));
    }
}
