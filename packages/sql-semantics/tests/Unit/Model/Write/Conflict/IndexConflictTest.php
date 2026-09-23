<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Write\Conflict\IndexConflict::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IndexConflictTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Write\Conflict\IndexConflict([]);
    }
}
