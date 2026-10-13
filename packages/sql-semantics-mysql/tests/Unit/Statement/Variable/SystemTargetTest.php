<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(SystemTarget::class)]
#[Medium]
final class SystemTargetTest extends TestCase
{
    public function testDeriveScalarRecordsTheTargetWithoutReadingIt(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $target = new SystemTarget(new Name('sql_mode'), VariableScope::Persist);
        $fact = $derivation->scalar($target, $derivation->environment());

        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: system variable @@PERSIST.sql_mode', $fact->type->missing[0]->describe());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testStructuredReportsAnAssignedOccurrence(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new SystemTarget(new Name('unknown_variable'), null, new Name('component')))->structured($derivation);

        self::assertEquals([new UnknownSystemVariable('component.unknown_variable', true, true)], $derivation->facts()->diagnostics);
    }

    public function testRenderPreservesAssignmentScopesAndInstances(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SET @@PERSIST_ONLY.component.variable_name = DEFAULT');

        self::assertSame('SET @@PERSIST_ONLY.`component`.variable_name = DEFAULT', $operation->toString());
    }

    public function testDeriveScalarLeavesReadDeprecationsToReadOccurrences(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context());
        $derivation->scalar(new SystemTarget(new Name('tx_isolation')), $derivation->environment());

        self::assertSame([], $derivation->facts()->warnings);
    }
}
