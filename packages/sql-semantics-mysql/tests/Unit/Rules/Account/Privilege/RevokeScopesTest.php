<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\Privilege\RevokeScopes;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RevokeScopes::class)]
#[Small]
final class RevokeScopesTest extends TestCase
{
    public function testDeferredDistinguishesRoutineObjectsAndWildcards(): void
    {
        $scope = new RevokeScopes();
        $all = [new M\Account\Privilege\Item\AllPrivileges()];
        $kind = M\Account\Privilege\Level\ObjectKind::Function;
        self::assertTrue($scope->deferred($all, $kind, new M\Account\Privilege\Level\CurrentDatabaseLevel()));
        self::assertTrue($scope->deferred($all, $kind, new M\Account\Privilege\Level\DatabaseLevel(new Name('d'))));
        self::assertFalse($scope->deferred($all, $kind, new M\Account\Privilege\Level\ObjectLevel(new QualifiedName(new Name('f')))));
        self::assertFalse($scope->deferred($all, M\Account\Privilege\Level\ObjectKind::Table, new M\Account\Privilege\Level\CurrentDatabaseLevel()));
        self::assertFalse($scope->deferred($all, $kind, new M\Account\Privilege\Level\GlobalLevel()));
        self::assertTrue($scope->deferred($all, $kind, new M\Account\Privilege\Level\GlobalLevel(), true));
    }

    public function testGlobalAllDoesNotAdmitOtherGlobalRoutinePrivileges(): void
    {
        $scope = new RevokeScopes();
        $level = new M\Account\Privilege\Level\GlobalLevel();
        $kind = M\Account\Privilege\Level\ObjectKind::Function;
        self::assertTrue($scope->globalAll([new M\Account\Privilege\Item\AllPrivileges()], $kind, $level));
        self::assertFalse($scope->globalAll([new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Execute)], $kind, $level));
    }
}
