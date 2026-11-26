<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyIndexSide::class)]
#[Small]
final class ForeignKeyIndexSideTest extends TestCase
{
    public function testOrderedSideOwnsItsFieldReferences(): void
    {
        $first = new Identifier('account_id');
        $constraint = new ForeignKeyConstraintIdentity(NativeAdditionFixture::owner('child'), new Identifier('original'));
        $scope = new ForeignKeyIndexSide($constraint, [&$first, new Identifier('tenant_id')], true);
        $first = new Identifier('unrelated');

        self::assertTrue($scope->parent);
        self::assertSame(['account_id', 'tenant_id'], array_column($scope->fields, 'value'));
    }
}
