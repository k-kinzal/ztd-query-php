<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ModernForms::class)]
#[Small]
final class ModernFormsTest extends TestCase
{
    public function testAuthenticationDistinguishesPasswordManagementFromAnOrdinaryAccount(): void
    {
        $forms = new ModernForms();
        $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u'));

        self::assertFalse($forms->authentication(new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification($account)));
        self::assertTrue($forms->authentication(new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification($account, discardOld: true)));
    }

    public function testRequiresRecognizesExtendedAndRepeatedLocking(): void
    {
        $forms = new ModernForms();
        $ordinary = new LockingClause(LockStrength::Update);
        $share = new LockingClause(LockStrength::Share);
        $nowait = new LockingClause(LockStrength::Update, action: LockedRowAction::Nowait);
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], locking: [new LockingClause(LockStrength::Update), new LockingClause(LockStrength::Update)]);

        self::assertFalse($forms->requires($ordinary));
        self::assertTrue($forms->requires($share));
        self::assertTrue($forms->requires($nowait));
        self::assertTrue($forms->requires($query));
    }

    public function testQueryLeavesNoFromFilteringToTheStatementPosition(): void
    {
        $forms = new ModernForms();
        $plain = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $filtered = new Select([], [new SelectExpression(new NumberLiteral('1'))], where: new NumberLiteral('1'));

        self::assertFalse($forms->query($plain));
        self::assertFalse($forms->query($filtered));
        self::assertFalse($forms->requires($filtered));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\QueryRoot($filtered)));
    }


    public function testRequiresDistinguishesModernHintsAndSpatialAggregation(): void
    {
        $forms = new ModernForms();
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint('batch')));
        self::assertFalse($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint('block')));
        self::assertTrue($forms->requires(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect));
        self::assertFalse($forms->requires(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count));
        self::assertTrue($forms->requires(\SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::Rollup));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction()));
    }

    public function testLockingDistinguishesSimpleUpdateFromShare(): void
    {
        $forms = new ModernForms();
        self::assertFalse($forms->locking(new LockingClause(LockStrength::Update)));
        self::assertTrue($forms->locking(new LockingClause(LockStrength::Share)));
    }

}
