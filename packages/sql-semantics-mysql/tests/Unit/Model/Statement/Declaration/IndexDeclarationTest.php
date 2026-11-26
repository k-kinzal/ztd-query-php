<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;

#[CoversClass(IndexDeclaration::class)]
#[Small]
final class IndexDeclarationTest extends TestCase
{
    public function testKeepsPhysicalNameAndRole(): void
    {
        $name = new Name('declared');
        $index = new IndexDeclaration($name, PhysicalIndexKind::Unique, true);

        self::assertSame($name, $index->name);
        self::assertSame(PhysicalIndexKind::Unique, $index->kind);
        self::assertTrue($index->wholeFields);
    }
}
