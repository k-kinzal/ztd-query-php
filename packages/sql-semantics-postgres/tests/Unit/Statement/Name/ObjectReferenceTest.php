<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;

#[CoversClass(ObjectReference::class)]
#[Small]
final class ObjectReferenceTest extends TestCase
{
    public function testADottedNameRefersToAnObject(): void
    {
        self::assertContains(ObjectReference::class, class_implements(DottedName::class));
    }
}
