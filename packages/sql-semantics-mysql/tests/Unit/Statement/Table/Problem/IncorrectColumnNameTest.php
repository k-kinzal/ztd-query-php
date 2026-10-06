<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IncorrectColumnName::class)]
#[Small]
final class IncorrectColumnNameTest extends TestCase
{
    public function testMessageQuotesTheName(): void
    {
        self::assertSame("Incorrect column name 'a '.", (new IncorrectColumnName(new Name('a ')))->message());
    }
}
