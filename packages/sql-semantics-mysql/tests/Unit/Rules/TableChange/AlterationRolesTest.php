<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableChange\AlterationRoles;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AlterationRoles::class)]
#[Small]
final class AlterationRolesTest extends TestCase
{
    public function testDeferredSeparatesAnInvocationConditionFromOrdinaryAlterations(): void
    {
        self::assertTrue((new AlterationRoles())->deferred([new M\Alter\Modifier\ValidationOption(true)]));
        self::assertFalse((new AlterationRoles())->deferred([new M\Alter\Command\Force()]));
        $this->expectExceptionMessage('An ordinary ALTER statement excludes actions with an intrinsic invocation-time condition.');
        new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Modifier\ValidationOption(true)]);
    }

    public function testInvalidValidationRequiresAnEligibleAction(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        self::assertFalse((new AlterationRoles())->invalidValidation([new M\Alter\Column\AddColumn($column), new M\Alter\Modifier\ValidationOption(true)]));
        self::assertTrue((new AlterationRoles())->invalidValidation([new M\Alter\Command\Force(), new M\Alter\Modifier\ValidationOption(false)]));
    }
}
