<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\InvalidInputException;
use Deriver\Query\Budget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidInputException::class)]
#[UsesClass(Budget::class)]
#[Small]
final class InvalidInputExceptionTest extends TestCase
{
    public function testPreservesItsSemanticContract(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('positive');
        new Budget(transfers: 0);
    }
}
