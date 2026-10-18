<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;

#[CoversClass(OperandKind::class)]
#[Small]
final class OperandKindTest extends TestCase
{
    public function testSeparatesFunctionQueryAndVariableConditions(): void
    {
        self::assertSame(['FunctionCall', 'Subquery', 'Variable', 'Row', 'StoredFunction'], array_map(static fn (OperandKind $kind): string => $kind->name, OperandKind::cases()));
    }
}
