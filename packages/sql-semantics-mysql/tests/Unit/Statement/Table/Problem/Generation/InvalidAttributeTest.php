<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InvalidAttribute::class)]
#[Small]
final class InvalidAttributeTest extends TestCase
{
    public function testMessageIdentifiesTheIncompatibleGenerationAttribute(): void
    {
        self::assertSame('Incorrect usage of STORAGE and generated column', (new InvalidAttribute(new Name('g'), 'STORAGE'))->message());
    }
}
