<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\DerivedInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;

#[CoversClass(DerivedInput::class)]
#[Medium]
final class DerivedInputTest extends TestCase
{
    public function testWrittenResolvesTheWriteHeadAndItsNestedReadDependencies(): void
    {
        $session = (new Instance())->connect();
        $input = new DerivedInput();

        self::assertSame(1046, $input->written('REPLACE t SET a=ABS()', $session, 21)?->getCode());
        self::assertSame(1046, $input->written('UPDATE t SET a=ABS()', $session, 20)?->getCode());
        self::assertSame(1046, $input->written('UPDATE d.t JOIN (SELECT 1 FROM u) c SET t.a=ABS()', $session, 50)?->getCode());
        self::assertNull($input->written('WITH c AS(SELECT 1) UPDATE d.t JOIN c SET t.a=ABS()', $session, 50));
        self::assertNull($input->written('UPDATE t SET a=ABS()', $session, 7));
        self::assertNull($input->written('UPDATE t SET a=', $session, 50));
        $session->variables->database = 'd';
        self::assertNull($input->written('REPLACE t SET a=ABS()', $session, 21));
    }

    public function testWrittenKeepsLegacyGrammarConditionsBeforeContextualDependencies(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $input = new DerivedInput();

        self::assertNull($input->written('UPDATE t SET a=CAST(1 AS TIME(7))', $session, 100));
        self::assertSame(1046, $input->written('UPDATE t SET a=ABS()', $session, 100)?->getCode());
    }

    public function testPrefixResolvesLegacyDependenciesBeforeAnUnfinishedCommand(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $input = new DerivedInput();

        self::assertSame(1046, $input->prefix('INSERT IGNORE INTO t VALUES (', 100, $session)?->getCode());
        self::assertSame(1046, $input->prefix('UPDATE IGNORE t SET a=', 100, $session)?->getCode());
        self::assertSame(1046, $input->prefix('SELECT 1 FROM t,', 100, $session)?->getCode());
        self::assertNull($input->prefix('SELECT 1 FROM d.t,', 100, $session));
        self::assertNull($input->prefix('SELECT 1 FROM `` ,', 100, $session));
        self::assertNull($input->prefix('SELECT 1 FROM t,', 10, $session));
        self::assertNull($input->prefix('SELECT 1 FROM t,', 100, (new Instance('5.7.44'))->connect()));
        $session->variables->database = 'd';
        self::assertNull($input->prefix('SELECT 1 FROM t,', 100, $session));
    }

    public function testBeforePreservesTheFirstRegisteredRelationCondition(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $input = new DerivedInput();
        $sql = 'SELECT * FROM t, (SELECT 1)';

        self::assertSame(1046, $input->before($sql, strlen($sql), $session)?->getCode());
        $session->variables->database = 'd';
        self::assertNull($input->before($sql, strlen($sql), $session));
        self::assertSame(1066, $input->before('SELECT * FROM t, t, (SELECT 1)', 27, $session)?->getCode());
        self::assertNull($input->before('SELECT * FROM (SELECT 1), t, t', 24, $session));
        self::assertNull($input->before($sql, strlen($sql), (new Instance('8.4.7'))->connect()));
    }

    public function testConditionsUseTheCompletedOperandAndRefusalBoundary(): void
    {
        $tree = (new MySqlParser('mysql-5.6.51'))->parse('SELECT * FROM d.t, u');
        $names = $tree->find('table_ident');
        $input = new DerivedInput();

        self::assertSame([], $input->conditions($tree, 100, ''));
        self::assertSame([], $input->conditions($names[0], 100, ''));
        self::assertSame([], $input->conditions($names[1], 10, ''));
        self::assertSame(1046, $input->conditions($names[1], 100, '')[0][1]->getCode());
    }
    public function testTargetsChecksTheIndependentDeleteHeader(): void
    {
        $session = (new Instance())->connect();
        $input = new DerivedInput();

        self::assertSame(1046, $input->targets('DELETE missing FROM (SELECT HIGH_PRIORITY 1) d', $session)?->getCode());
        self::assertNull($input->targets('DELETE d.missing FROM (SELECT HIGH_PRIORITY 1) d', $session));
        self::assertNull($input->targets('CREATE PROCEDURE p() DELETE missing FROM (SELECT HIGH_PRIORITY 1) d', $session));
    }

    public function testRefusalPreservesTheOuterTargetDatabaseRequirement(): void
    {
        $failure = new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority), 42, false);
        $session = (new Instance())->connect();

        self::assertSame(1046, (new DerivedInput())->refusal($failure, 'DELETE a FROM (SELECT HIGH_PRIORITY 1) d', $session)?->getCode());
    }

    public function testTargetsDoesNotOverrideARefusalBeforeTheTargetListCompletes(): void
    {
        $session = (new Instance())->connect();
        $input = new DerivedInput();

        self::assertNull($input->targets('DELETE a, d.b FROM (SELECT ABS()) AS x', $session, 11));
        self::assertSame(1046, $input->targets('DELETE a, d.b FROM (SELECT ABS()) AS x', $session, 13)?->getCode());
    }

}
