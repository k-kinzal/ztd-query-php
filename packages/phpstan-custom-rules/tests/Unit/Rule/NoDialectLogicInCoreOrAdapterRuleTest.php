<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoDialectLogicInCoreOrAdapterRule;

/**
 * @extends RuleTestCase<NoDialectLogicInCoreOrAdapterRule>
 */
#[CoversClass(NoDialectLogicInCoreOrAdapterRule::class)]
#[Medium]
final class NoDialectLogicInCoreOrAdapterRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDialectLogicInCoreOrAdapterRule();
    }

    public function testRejectsDialectLogicInCoreAndAdapters(): void
    {
        $message = 'Core and database adapters must delegate dialect-specific parsing, rendering, and metadata interpretation through platform contracts.';

        $this->analyse([
            __DIR__ . '/../../Fixture/PdoDialectLogicFixture.php',
            __DIR__ . '/../../Fixture/MysqliDialectLogicFixture.php',
            __DIR__ . '/../../Fixture/CoreDialectDependencyFixture.php',
            __DIR__ . '/../../Fixture/PdoAdapterBoundaryFixture.php',
            __DIR__ . '/../../Fixture/MysqliAdapterBoundaryFixture.php',
            __DIR__ . '/../../Fixture/CompositionRootNameCollisionFixture.php',
            __DIR__ . '/../../../../ztd-query-mysqli-adapter/src/MysqliStatementBindingBridge.php',
        ], [
            [$message, 11],
            [$message, 11],
            [$message, 13],
            [$message, 13],
            [$message, 16],
            [$message, 18],
            [$message, 19],
            [$message, 21],
            [$message, 23],
            [$message, 24],
            [$message, 26],
            [$message, 26],
            [$message, 28],
            [$message, 29],
            [$message, 31],
            [$message, 31],
            [$message, 34],
            [$message, 34],
            [$message, 34],
            [$message, 36],
            [$message, 39],
            [$message, 41],
            [$message, 44],
            [$message, 46],
            [$message, 49],
            [$message, 53],
            [$message, 54],
            [$message, 54],
            [$message, 55],
            [$message, 56],
        ]);
    }
}
