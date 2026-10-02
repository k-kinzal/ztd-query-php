<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Account\AccountRules;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(AccountRules::class)]
#[Small]
final class AccountRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new AccountRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL account family: statement');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testDefinitionReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new AccountRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL account family: definition');

        $rules->definition(new Form(new Node('rule', 0, []), 'rule:'));
    }

    public function testRenameUsersReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new AccountRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL account family: renameUsers');

        $rules->renameUsers(new Node('rule', 0, []));
    }

    public function testSetPasswordReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new AccountRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL account family: setPassword');

        $rules->setPassword(new Form(new Node('rule', 0, []), 'rule:'));
    }
}
