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
}
