<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Resolution\TriggerNamespace;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TriggerNamespace::class)]
#[Small]
final class TriggerNamespaceTest extends TestCase
{
    public function testOwnerPromotesAnUnqualifiedNameForATemporaryTable(): void
    {
        $inspection = new Inspection();
        $main = $inspection->diagnose('CREATE TABLE watched (main_value)');
        $temp = $inspection->diagnose('CREATE TEMP TABLE watched (temp_value)');
        $trigger = $inspection->diagnose('CREATE TRIGGER t INSERT ON watched BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        self::assertSame('temp', (new TriggerNamespace())->owner($inspection->context([$main, $temp]), $trigger->name->schema, $trigger->temporary, $trigger->table->name)->value);
    }

    public function testWatchedUsesThePersistentTriggerOwnerForAnUnqualifiedTable(): void
    {
        $inspection = new Inspection();
        $trigger = $inspection->diagnose('CREATE TRIGGER main.t UPDATE ON watched BEGIN SELECT 1; END')->statement;
        $scope = new TriggerNamespace();

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        $owner = $scope->owner($inspection->context(), $trigger->name->schema, $trigger->temporary, $trigger->table->name);
        $name = $scope->watched($trigger->table->name, $owner);
        self::assertSame('main', $name->schema?->value);
        self::assertSame('watched', $name->name->value);
    }

    public function testKnownUsesTheNativeComparisonOfAttachmentNames(): void
    {
        $dialect = new \SqlSemantics\Platform\Sqlite\Semantics(
            searchPath: new \SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'Work'),
        );
        $context = $dialect->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $names = new TriggerNamespace();

        self::assertSame('Work', $names->known($context, new Name('WORK'))?->value);
        self::assertNull($names->known($context, new Name('unattached')));
    }

    public function testValidatePathRejectsRepeatedNativeSchemaNames(): void
    {
        $dialect = new \SqlSemantics\Platform\Sqlite\Semantics(
            searchPath: new \SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'work', 'WORK'),
        );
        $this->expectExceptionMessage('Trigger resolution requires distinct schema names.');

        $context = $dialect->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        (new TriggerNamespace())->validatePath($context);
    }
}
