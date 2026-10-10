<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\VariableAccess;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(VariableAccess::class)]
#[Small]
final class VariableAccessTest extends TestCase
{
    public function testReadRefusesAScopeTheVariableLacks(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $access = new VariableAccess();

        self::assertNotNull($access->read('autocommit', null, $derivation));
        self::assertNull($access->read('timestamp', VariableScope::Global, $derivation));
        self::assertNull($access->read('version', VariableScope::Session, $derivation));
        self::assertEquals([new VariableMisuse(VariableRule::GlobalOfSessionVariable, 'timestamp'), new VariableMisuse(VariableRule::SessionOfGlobalVariable, 'version')], $derivation->facts()->diagnostics);
    }

    public function testAssignChecksWritabilityBeforeScope(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $access = new VariableAccess();
        $access->assign('version', VariableScope::Session, $derivation);
        $access->assign('timestamp', VariableScope::Global, $derivation);
        $access->assign('max_connections', null, $derivation);
        $access->assign('max_allowed_packet', VariableScope::Session, $derivation);
        $access->assign('autocommit', null, $derivation);

        self::assertEquals([VariableRule::ReadOnly, VariableRule::SetGlobalOfSessionVariable, VariableRule::SetSessionOfGlobalVariable, VariableRule::SessionReadOnly], array_map(static fn ($diagnostic) => $diagnostic instanceof VariableMisuse ? $diagnostic->rule : null, $derivation->facts()->diagnostics));
    }

    public function testAssignRetainsWhetherTheUnknownVariableWasWritten(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $access = new VariableAccess();
        $access->assign('nosuch', null, $derivation);
        $access->read('nosuch', null, $derivation);

        self::assertEquals([new UnknownSystemVariable('nosuch', true), new UnknownSystemVariable('nosuch')], $derivation->facts()->diagnostics);
    }

    public function testFindReportsAnUnknownVariable(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertNull((new VariableAccess())->find('nosuch', $derivation));
        self::assertEquals([new UnknownSystemVariable('nosuch')], $derivation->facts()->diagnostics);
    }
}
