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
    public function testDefinitionRecognizesLoadSourceFeaturesBeforeCombiningTheirOperands(): void
    {
        $forms = new ModernForms();
        self::assertFalse($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput(false, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('f'))));
        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput(false, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::S3, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('f'))));
        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions(bulk: true)));
    }

    public function testDefinitionDistinguishesAccountHostLimits(): void
    {
        $forms = new ModernForms();
        $user = new \SqlSemantics\Statement\Identifier\Name('u');

        self::assertFalse($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName($user, new \SqlSemantics\Statement\Identifier\Name(str_repeat('h', 60)))));
        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName($user, new \SqlSemantics\Statement\Identifier\Name(str_repeat('h', 61)))));
    }

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

    public function testRequiresRecognizesCurrentReplicationOperands(): void
    {
        $forms = new ModernForms();
        $current = new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RelayLogPosition, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4'));

        self::assertTrue($forms->requires($current));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current)));
        self::assertFalse($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy)));
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

    public function testExplainDistinguishesModernOptionsFromAnOrdinaryRequest(): void
    {
        $forms = new ModernForms();
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))]));

        self::assertFalse($forms->explain(new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query)));
        self::assertTrue($forms->explain(new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query, new \SqlSemantics\Statement\Identifier\Name('tReE'))));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query, database: new \SqlSemantics\Statement\Identifier\Name('d'))));
        self::assertTrue($forms->explain(new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1'), format: new \SqlSemantics\Statement\Identifier\Name('TREE'))));
        self::assertTrue($forms->explain(new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query, format: new \SqlSemantics\Statement\Identifier\Name('JSON'), into: new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('plan')))));
    }

    public function testExplainModernOptionsRejectLegacyOperands(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))]));
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0');

        new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query, database: new \SqlSemantics\Statement\Identifier\Name('d'));
    }

    public function testDefinitionRecognizesInstantAlter(): void
    {
        self::assertTrue((new ModernForms())->definition(new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(new \SqlSemantics\Statement\Identifier\Name('INSTANT'))));
        self::assertFalse((new ModernForms())->definition(new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(null)));
    }

}
