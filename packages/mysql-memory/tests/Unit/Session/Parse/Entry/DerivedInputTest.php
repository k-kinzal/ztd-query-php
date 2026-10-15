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
}
