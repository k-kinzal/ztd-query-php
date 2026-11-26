<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Visibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityEdit;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind;

#[CoversClass(IndexVisibilityEdit::class)]
#[Small]
final class IndexVisibilityEditTest extends TestCase
{
    public function testToStringPreservesTheQuotedPhysicalNameAndRequest(): void
    {
        $target = new Identifier('Index`Name');
        $kind = SecondaryIndexKind::Unique;

        self::assertSame('ALTER INDEX `Index``Name` VISIBLE', (new IndexVisibilityEdit($target, $kind, true))->toString());
        self::assertSame('ALTER INDEX `Index``Name` INVISIBLE', (new IndexVisibilityEdit($target, $kind, false))->toString());
    }
}
