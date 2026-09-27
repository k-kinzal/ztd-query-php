<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\NameSite;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\ReferenceKind;

#[CoversClass(NameSite::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[Small]
final class NameSiteTest extends TestCase
{
    public function testKeepsTheValueNameKindAndCondition(): void
    {
        $value = new Name('users');
        $site = new NameSite($value, ['main', 'users'], ReferenceKind::Drop, true);
        self::assertSame($value, $site->value);
        self::assertSame(['main', 'users'], $site->name);
        self::assertSame(ReferenceKind::Drop, $site->kind);
        self::assertTrue($site->conditional);
        self::assertFalse((new NameSite($value, ['users'], ReferenceKind::Dependency))->conditional);
    }
}
