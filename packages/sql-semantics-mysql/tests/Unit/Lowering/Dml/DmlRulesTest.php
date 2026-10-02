<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Dml\DmlRules;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(DmlRules::class)]
#[Small]
final class DmlRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new DmlRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL dml family: statement');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testDuplicateHandlingReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new DmlRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL dml family: duplicateHandling');

        $rules->duplicateHandling(new Node('rule', 0, []));
    }

    public function testFileFormatReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new DmlRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL dml family: fileFormat');

        $rules->fileFormat(new Node('rule', 0, []), new Node('rule', 0, []), new Node('rule', 0, []));
    }

    public function testDeleteTargetsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new DmlRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL dml family: deleteTargets');

        $rules->deleteTargets(new Node('rule', 0, []));
    }

    public function testRowValuesReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new DmlRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL dml family: rowValues');

        $rules->rowValues(new Node('rule', 0, []));
    }
}
