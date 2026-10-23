<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;

#[CoversClass(LoadConflict::class)]
#[Small]
final class LoadConflictTest extends TestCase
{
    public function testMessageDescribesTheIncompatibleAlgorithm(): void
    {
        self::assertSame('Incorrect usage of LOAD XML and BULK Algorithm', (new LoadConflict(LoadRule::XmlBulk))->message());
    }
}
