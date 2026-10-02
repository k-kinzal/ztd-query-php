<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\TableDefinitionRules;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(TableDefinitionRules::class)]
#[Small]
final class TableDefinitionRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: statement');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testDefinitionReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: definition');

        $rules->definition(new Form(new Node('rule', 0, []), 'rule:'));
    }

    public function testCreateViewReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: createView');

        $rules->createView(new Node('rule', 0, []), null, null);
    }

    public function testTableElementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: tableElement');

        $rules->tableElement(new Node('rule', 0, []));
    }

    public function testTableElementsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: tableElements');

        $rules->tableElements(new Node('rule', 0, []));
    }

    public function testColumnSpecificationReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: columnSpecification');

        $rules->columnSpecification(new Node('rule', 0, []));
    }

    public function testTableOptionsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: tableOptions');

        $rules->tableOptions(new Node('rule', 0, []));
    }

    public function testVisibleReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: visible');

        $rules->visible(new Node('rule', 0, []));
    }

    public function testEnforcedReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new TableDefinitionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: enforced');

        $rules->enforced(new Node('rule', 0, []));
    }
}
