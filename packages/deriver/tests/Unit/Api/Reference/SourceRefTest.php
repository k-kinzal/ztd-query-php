<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Api\Reference\SourceRef::class)]
#[Small]
final class SourceRefTest extends TestCase
{
    public function testIdPreservesTheSemanticContract(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('half-open');
        new \Deriver\Api\Reference\SourceRef('s', 'a.php', 4, 3);
    }
}
