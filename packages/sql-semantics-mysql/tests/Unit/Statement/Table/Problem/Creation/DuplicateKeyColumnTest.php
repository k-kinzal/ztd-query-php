<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DuplicateKeyColumn::class)]
#[Medium]
final class DuplicateKeyColumnTest extends TestCase
{
    public function testMessagePreservesTheRejectedName(): void
    {
        $name = new Name('primary');
        $problem = new DuplicateKeyColumn($name);
        self::assertSame($name, $problem->column);
        self::assertSame('Duplicate key column primary.', $problem->message());
    }
}
