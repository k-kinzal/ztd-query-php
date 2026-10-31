<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement;

#[CoversClass(UnattachedEnforcement::class)]
#[Small]
final class UnattachedEnforcementTest extends TestCase
{
    public function testMessageDescribesTheMissingAttributeRelationship(): void
    {
        $problem = new UnattachedEnforcement(12);
        self::assertSame(12, $problem->offset);
        self::assertSame('Column enforcement follows a CHECK constraint or its enforcement chain.', $problem->message());
    }
}
