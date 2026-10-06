<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::class)]
#[Small]
final class PrivilegeObjectKindTest extends TestCase
{
    public function testSubjectIsTheWordOfTheServerMessages(): void
    {
        self::assertSame('relation', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Relation->subject());
        self::assertSame('foreign-data wrapper', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::ForeignDataWrapper->subject());
        self::assertSame('large object', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::LargeObject->subject());
    }
}
