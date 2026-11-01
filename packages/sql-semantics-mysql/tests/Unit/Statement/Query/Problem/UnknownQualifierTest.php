<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(UnknownQualifier::class)]
#[Small]
final class UnknownQualifierTest extends TestCase
{
    public function testMessageNamesTheQualifierAsWritten(): void
    {
        self::assertSame("Unknown table 'q'", (new UnknownQualifier(new QualifiedName(new Name('q'))))->message());
        self::assertSame("Unknown table 'd.q'", (new UnknownQualifier(new QualifiedName(new Name('q'), new Name('d'))))->message());
    }
}
