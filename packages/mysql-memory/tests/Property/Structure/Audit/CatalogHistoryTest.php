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
     * Establishes the trigger under the inspected identity on a populated table.
     */
    public function testSetupKeepsTheInspectedTriggerNameAndEstablishesItsTable(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('trigger.name'));
        $node = new M\Utility\Show\Program\ShowCreateTrigger($name);
        $steps = (new CatalogHistory())->steps($node, 1360);
        self::assertCount(5, $steps);
        self::assertInstanceOf(M\Table\CreateTable::class, $steps[1]);
        self::assertInstanceOf(M\Routine\CreateTrigger::class, $steps[4]);
        self::assertSame($name, $steps[4]->name);
        self::assertSame($steps[1]->name, $steps[4]->table->name);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
    }

    /**
     * Creates a disabled event under the exact referenced name.
     */
    public function testStepsKeepTheEventIdentityAndDisableItsScheduler(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('event.name'));
        $node = new M\Routine\AlterEvent($name, completion: M\Routine\Event\Completion::Preserve);
        $steps = (new CatalogHistory())->steps($node, 1539);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Routine\CreateEvent::class, $steps[0]);
        self::assertSame($name, $steps[0]->name);
        self::assertSame(M\Routine\Event\EventStatus::Disable, $steps[0]->status);
        self::assertSame([], (new CatalogHistory())->steps($node, 1064));
    }

    /**
     * Changes relation kind while retaining the SHOW operand identity.
     */
    public function testStepsReplaceTheFixtureTableWithTheReferencedView(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('view.name'));
        $node = new M\Utility\Show\Schema\ShowCreateView(new M\Utility\Show\InspectedTable($name));
        $steps = (new CatalogHistory())->steps($node, 1347);
        self::assertCount(2, $steps);
        self::assertInstanceOf(M\Alter\DropTable::class, $steps[0]);
        self::assertInstanceOf(M\View\CreateView::class, $steps[1]);
        self::assertSame($name, $steps[0]->tables[0]->name);
        self::assertSame($name, $steps[1]->definition->name);
    }

    /**
     * Changes the target relation kind without replacing an ALTER definition or its query.
     */
    public function testStepsKeepTheAlterViewTargetAndBody(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('view.name'));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('9'))]);
        $node = new M\View\AlterView(new M\View\ViewDefinition($name, $query));
        $steps = (new CatalogHistory())->steps($node, 1347);
        self::assertCount(2, $steps);
        self::assertInstanceOf(M\View\CreateView::class, $steps[1]);
        self::assertSame($name, $steps[1]->definition->name);
        self::assertSame($query, $node->definition->query);
    }

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
