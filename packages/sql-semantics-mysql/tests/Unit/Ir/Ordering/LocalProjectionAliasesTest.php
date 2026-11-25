<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(LocalProjectionAliases::class)]
#[Small]
final class LocalProjectionAliasesTest extends TestCase
{
    public function testCheckPermitsRemappingAnEffectiveName(): void
    {
        $value = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('n'))]);
        (new LocalProjectionAliases())->check($value);

        self::assertSame('SELECT 1 AS `n`', $value->toString());
    }

    public function testRequiresRequestedFindsAnAliasWithNativeDiagnostics(): void
    {
        $value = new Select([new ResultColumn(new IntegerLiteral('1'), new RequestedResultAlias(' n'))]);

        self::assertTrue((new LocalProjectionAliases())->requiresRequested($value));
    }

    public function testCheckDerivedPermitsASeparateModernAliasList(): void
    {
        $child = new Select([new ResultColumn(new IntegerLiteral('1'), new RequestedResultAlias(' n'))]);
        $aliases = new LocalProjectionAliases();
        $aliases->checkDerived($child);

        self::assertTrue($aliases->requiresRequested($child));
    }

    public function testNeedsInheritedUsesTheNativeHeadingOnMySql57(): void
    {
        $child = new Select(
            [new ResultColumn(new IntegerLiteral('1'), new RequestedResultAlias(' item'))],
            release: GrammarRelease::MySql5744,
        );
        $aliases = new LocalProjectionAliases();
        $aliases->checkDerived($child, true);

        self::assertTrue($aliases->needsInherited($child));
    }
}
