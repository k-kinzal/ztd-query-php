<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Lookup;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Lookup\Preparation;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use MySqlMemory\Plan\Path\Transform\Prepare;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preparation::class)]
#[Small]
final class PreparationTest extends TestCase
{
    public function testPlanLeavesUnreadMySql56PredicatesDeferred(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20)); INSERT INTO t VALUES (1,'apple')");
        $session->query('SELECT CASE WHEN 0 THEN (0,1) IN (SELECT s,id FROM t) ELSE 7 END');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testPlanKeepsLegacyPreparationOutsideZeroRowLimits(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20))');
        $operation = $session->analyze('SELECT (0,1) IN (SELECT s,id FROM t) LIMIT 0');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->query, $operation->statementInformation(), $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $plan = $planner->query($operation->query, null);

        self::assertInstanceOf(Prepare::class, $plan->root);
        self::assertTrue($plan->root->early);
        self::assertInstanceOf(\MySqlMemory\Plan\Path\Transform\Project::class, $plan->root->input);
        self::assertInstanceOf(\MySqlMemory\Plan\Path\Transform\Limit::class, $plan->root->input->input);
    }

    public function testGuardFindsTheOuterConstantTableBeforeSubqueryPreparation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20))');
        $operation = $session->analyze('SELECT (0,1) IN (SELECT s,id FROM t) FROM t WHERE id=99');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->query, $operation->statementInformation(), $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $plan = $planner->query($operation->query, null);

        self::assertInstanceOf(Prepare::class, $plan->root);
        self::assertInstanceOf(KeyLookup::class, $plan->root->guard);
    }
}
