<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Write\Conflict\DoUpdate::class)]
final class DoUpdateTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Write\Conflict\DoUpdate(new \SqlSemantics\Model\Write\Conflict\AnyConflict(), [], null, new Node('conflict', 0, []));
    }
}
