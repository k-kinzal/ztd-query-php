<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\JoinText;
use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;

#[CoversClass(JoinText::class)]
#[Small]
final class JoinTextTest extends TestCase
{
    public function testConditionUsesResolvedColumnOccurrences(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(x INT); CREATE TABLE u(x INT)');
        $operation = $session->analyze('SELECT * FROM t NATURAL JOIN u');
        self::assertInstanceOf(QueryRoot::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        $join = $operation->statement->query->from;
        self::assertInstanceOf(JoinedTable::class, $join);
        $writer = new JoinText(new ViewText($operation->facts, '', 'd'));
        self::assertSame('(`d`.`t`.`x` = `d`.`u`.`x`)', $writer->condition($join));
        self::assertSame(['x' => '`d`.`t`.`x`'], $writer->columns($join->left));
    }
    public function testColumnsUsesDerivedOutputNames(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT * FROM (SELECT 1 AS x) q');
        self::assertInstanceOf(QueryRoot::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        $from = $operation->statement->query->from;
        self::assertNotNull($from);
        $writer = new JoinText(new ViewText($operation->facts, '', ''));
        self::assertSame(['x' => '`q`.`x`'], $writer->columns($from));
    }

}
