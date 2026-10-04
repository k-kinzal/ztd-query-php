<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind::class)]
#[Medium]
final class ReplicaIdentityKindTest extends TestCase
{
    public function testKeywordsSpellTheIdentity(): void
    {
        self::assertSame([
          0 => 'USING',
          1 => 'INDEX',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind::Index->keywords());
    }
}
