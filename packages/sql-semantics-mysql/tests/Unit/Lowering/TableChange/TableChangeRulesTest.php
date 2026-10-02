<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\TableChangeRules;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(TableChangeRules::class)]
#[Small]
final class TableChangeRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableChangeRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table change family: statement');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testDefinitionReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableChangeRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table change family: definition');

        $rules->definition(new Form(new Node('rule', 0, []), 'rule:'));
    }

    public function testPartitioningReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableChangeRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table change family: partitioning');

        $rules->partitioning(new Node('rule', 0, []));
    }

    public function testAlterOptionsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableChangeRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table change family: alterOptions');

        $rules->alterOptions(new Node('rule', 0, []));
    }

    public function testPartitionNamesReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableChangeRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table change family: partitionNames');

        $rules->partitionNames(new Node('rule', 0, []));
    }
}
