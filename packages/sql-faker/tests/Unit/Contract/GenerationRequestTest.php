<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;

#[CoversClass(GenerationRequest::class)]
final class GenerationRequestTest extends TestCase
{
    public function testConstructsReadonlyGenerationRequest(): void
    {
        $request = new GenerationRequest('stmt', 7, 12);

        self::assertSame('stmt', $request->startRule);
        self::assertSame(7, $request->seed);
        self::assertSame(12, $request->maxDepth);
    }

    public function testRejectsEmptyStartRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('startRule must be a non-empty string when provided.');

        new GenerationRequest('');
    }
}
