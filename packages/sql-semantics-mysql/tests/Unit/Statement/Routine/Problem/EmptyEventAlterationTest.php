<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EmptyEventAlteration::class)]
#[Small]
final class EmptyEventAlterationTest extends TestCase
{
    public function testMessageDescribesTheMissingChange(): void
    {
        self::assertSame('ALTER EVENT changes at least one event property after its name.', (new EmptyEventAlteration(new QualifiedName(new Name('e'))))->message());
    }

    public function testANegativeInputPositionIsRejected(): void
    {
        $this->expectExceptionMessage('The event input position is nonnegative.');

        new EmptyEventAlteration(new QualifiedName(new Name('e')), offset: -1);
    }

    public function testACatalogQualifierIsRejected(): void
    {
        $this->expectExceptionMessage('An event name has at most a database qualifier.');

        new EmptyEventAlteration(new QualifiedName(new Name('e'), new Name('db'), new Name('catalog')));
    }
}
