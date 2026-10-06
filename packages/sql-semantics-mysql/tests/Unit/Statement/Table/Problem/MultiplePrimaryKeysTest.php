<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;

#[CoversClass(MultiplePrimaryKeys::class)]
#[Small]
final class MultiplePrimaryKeysTest extends TestCase
{
    public function testMessageStatesTheProblem(): void
    {
        self::assertSame('Multiple primary key defined.', (new MultiplePrimaryKeys())->message());
    }
}
