<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;

#[CoversClass(SetQuantifier::class)]
#[Small]
final class SetQuantifierTest extends TestCase
{
    public function testCasesSpellTheQuantifiers(): void
    {
        self::assertSame(['DISTINCT', 'ALL'], array_column(SetQuantifier::cases(), 'value'));
    }
}
