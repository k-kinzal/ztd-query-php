<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;

#[CoversClass(QualifiedName::class)]
#[Small]
final class QualifiedNameTest extends TestCase
{
    public function testToStringPreservesTheOrderAndQuotingOfNamespaces(): void
    {
        $name = new QualifiedName(new Name('table.name', Quote::Double), new Name('app'), new Name('db'));
        self::assertSame('db.app."table.name"', $name->toString());
    }

}
