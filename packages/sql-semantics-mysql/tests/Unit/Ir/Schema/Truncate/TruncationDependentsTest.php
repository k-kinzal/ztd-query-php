<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncationDependents;

#[CoversClass(TruncationDependents::class)]
#[Medium]
final class TruncationDependentsTest extends TestCase
{
    public function testCheckAdmitsANativeSelfReference(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('parent'));
        $target = new TableIdentity($name, ExternalRelationKind::BaseTable);
        $child = new TableIdentity($name, ExternalRelationKind::BaseTable);
        $constraint = new TruncationDependents();

        $constraint->check($target, [$child]);

        self::assertSame($target->quoted(), $child->quoted());
    }
}
