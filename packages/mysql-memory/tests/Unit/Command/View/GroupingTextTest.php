<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\GroupingText;
use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(GroupingText::class)]
#[Small]
final class GroupingTextTest extends TestCase
{
    public function testClauseWritesTheResolvedRollupList(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(x INT)');
        $operation = $session->analyze('SELECT x FROM t GROUP BY x WITH ROLLUP');
        self::assertInstanceOf(QueryRoot::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        $writer = new GroupingText((new ViewText($operation->facts, '', 'd'))->expressions);
        self::assertSame(' group by `d`.`t`.`x` with rollup', $writer->clause($operation->statement->query->groupBy));
        self::assertSame('', $writer->clause(null));
    }
}
