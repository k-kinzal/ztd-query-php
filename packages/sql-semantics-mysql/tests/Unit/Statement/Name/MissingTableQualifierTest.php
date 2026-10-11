<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(MissingTableQualifier::class)]
#[Small]
final class MissingTableQualifierTest extends TestCase
{
    public function testMessageRetainsTheWrittenTableQualifier(): void
    {
        self::assertSame('Table t is not visible.', (new MissingTableQualifier(new Name('a'), new QualifiedName(new Name('t'))))->message());
        self::assertSame('Table d.t is not visible.', (new MissingTableQualifier(new Name('a'), new QualifiedName(new Name('t'), new Name('d'))))->message());
    }
}
