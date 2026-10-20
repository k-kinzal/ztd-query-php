<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\CatalogHistory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Verifies that catalog witnesses repeat the original referenced identity.
 */
#[CoversNothing]
#[Small]
final class CatalogHistoryTest extends TestCase
{
    /**
     * Existing anonymous users satisfy conditional role definitions without changing them.
     */
    public function testSetupRetainsTheConditionalAnonymousRoleDefinition(): void
    {
        $account = new M\Name\AccountName(new Name(''));
        $node = new M\Account\CreateRole(true, [$account]);
        $steps = (new CatalogHistory())->steps($node, 1396);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[0]);
        self::assertSame($account, $steps[0]->users[0]->user);
    }

    /**
     * Keeps the exact role and recipient operands in the setup statements.
     */
    public function testSetupUsesTheOriginalRoleAndRecipientObjects(): void
    {
        $role = new M\Name\AccountName(new Name('reader'));
        $user = new M\Name\AccountName(new Name('app'));
        $node = new M\Account\Privilege\GrantRoles([new M\Account\Privilege\Item\GrantedRole($role)], [$user]);
        $steps = (new CatalogHistory())->steps($node, 3523);
        self::assertCount(2, $steps);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[0]);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[1]);
        self::assertSame($role, $steps[0]->users[0]->user);
        self::assertSame($user, $steps[1]->users[0]->user);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
    }

    /**
     * Grants the selected roles before applying the unchanged default-role request.
     */
    public function testSetupEstablishesTheDefaultRoleGrantBeforeRepeatingTheInput(): void
    {
        $role = new M\Name\AccountName(new Name('reader'));
        $user = new M\Name\AccountName(new Name('app'));
        $node = new M\Account\AlterDefaultRole(false, $user, new M\Account\User\RoleSelection(M\Account\User\RoleSet::Named, [$role]));
        $steps = (new CatalogHistory())->steps($node, 3530);
        self::assertCount(3, $steps);
        self::assertInstanceOf(M\Account\Privilege\GrantRoles::class, $steps[2]);
        self::assertSame($role, $steps[2]->roles[0]->role);
        self::assertSame($user, $steps[2]->users[0]);
    }

    /**
     * Adds the missing catalog entry without changing the tested structure.
     */
    public function testSetupRetainsTheOriginalForeignServerIdentity(): void
    {
        $name = new Name("catalog ' あ");
        $node = new M\Server\ForeignServer\DropServer(false, $name);
        $steps = (new CatalogHistory())->steps($node, 1477);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Server\ForeignServer\CreateServer::class, $steps[0]);
        self::assertSame($name, $steps[0]->name);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
        self::assertSame([], (new CatalogHistory())->steps(new M\Literal\NullLiteral(), 1477));
    }
    /**
     * Establishes a user resource group under the exact original name operand.
     */
    public function testSetupRetainsTheResourceGroupIdentity(): void
    {
        $name = new M\Account\ResourceGroup\ResourceGroupName(new Name(''));
        $node = new M\Account\ResourceGroup\SetResourceGroup($name);
        $steps = (new CatalogHistory())->steps($node, 3651);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\ResourceGroup\CreateResourceGroup::class, $steps[0]);
        self::assertSame($name, $steps[0]->name);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
    }
}
