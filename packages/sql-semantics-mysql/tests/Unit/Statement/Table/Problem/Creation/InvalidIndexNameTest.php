<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InvalidIndexName::class)]
#[Medium]
final class InvalidIndexNameTest extends TestCase
{
    public function testMessagePreservesTheRejectedName(): void
    {
        $name = new Name('primary');
        $problem = new InvalidIndexName($name);
        self::assertSame($name, $problem->name);
        self::assertSame("Incorrect index name 'primary'.", $problem->message());
    }
}
