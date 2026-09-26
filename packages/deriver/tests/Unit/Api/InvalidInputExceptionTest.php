<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[Small]
final class InvalidInputExceptionTest extends TestCase
{
    public function testPreservesItsSemanticContract(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('positive');
        new \Deriver\Api\Query\Budget(transfers: 0);
    }
}
