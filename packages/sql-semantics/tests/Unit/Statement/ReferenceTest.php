<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;

#[CoversClass(Reference::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[Small]
final class ReferenceTest extends TestCase
{
    public function testKeepsTheValueNameKindAndResolution(): void
    {
        $value = new Name('users');
        $reference = new Reference($value, ['main', 'users'], ReferenceKind::Drop, null, null, true);
        self::assertSame($value, $reference->value);
        self::assertSame(['main', 'users'], $reference->name);
        self::assertSame(ReferenceKind::Drop, $reference->kind);
        self::assertNull($reference->declaration);
        self::assertNull($reference->table);
        self::assertTrue($reference->conditional);
    }

}
