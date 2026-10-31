<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Clashes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Clashes::class)]
#[Small]
final class ClashesTest extends TestCase
{
    public function testNestedRefusesATableNamedTwiceInANestedJoinBeforeItsUnionInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'a'");

        $session->query('SELECT * FROM (d.a, (b, a) UNION SELECT 1)');
    }

    public function testNestedLeavesTablesOfOtherDatabasesAndDerivedTablesToTheSyntaxError(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);

        $session->query('SELECT * FROM (x.a, y.a, (SELECT 1) AS a UNION SELECT 1)');
    }

    public function testDerivedAnswersTheNestedJoinsOfABlockInnermostFirst(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse('SELECT * FROM (a, (b, c), (SELECT 1) AS d)');

        self::assertCount(2, (new Clashes())->derived($tree->find('table_reference_list')[0], true));
    }

    public function testQueryAnswersTheSelectOfADerivedTable(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse('SELECT * FROM (SELECT 1) AS d, (a, b)');
        $derived = $tree->find('select_derived');

        self::assertSame([true, false], [(new Clashes())->query($derived[0]) !== null, (new Clashes())->query($derived[1]) !== null]);
    }

    public function testTopAnswersTheReferencesAtTheLevelOfAList(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse('SELECT * FROM (a, b, c)');

        self::assertCount(3, (new Clashes())->top($tree->find('select_derived')[0]->find('derived_table_list')[0]));
    }

    public function testFactorsAnswersTheDatabaseAliasAndOffsetOfEachTable(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse('SELECT * FROM x.a, `b` AS q, (SELECT 1) AS d WHERE 1 IN (SELECT 1 FROM e)');

        self::assertSame([['x', 'a', 14], ['db', 'q', 19], ['', 'd', 29]], (new Clashes())->factors($tree->find('table_reference_list')[0], 'db'));
    }

    public function testClashAnswersTheFirstAliasWrittenTwiceUpToAnOffset(): void
    {
        $factors = [['d', 'a', 0], ['d', 'b', 5], ['e', 'a', 10], ['d', 'a', 15]];

        self::assertSame([null, 'a'], [(new Clashes())->clash($factors, 12), (new Clashes())->clash($factors, 20)]);
    }

    public function testNestedAnswersTheAliasAndTheEndOfTheNestedJoin(): void
    {
        $text = 'SELECT * FROM (a, a UNION SELECT 1)';
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse($text);

        self::assertSame(['a', 18], (new Clashes())->nested($tree, new RuntimeException('refused'), 'd'));
    }

    public function testTargetsRefusesATableDeletedFromTwiceBeforeANestedJoinUnionInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'x'");

        $session->query('DELETE x.*, d.x FROM (t1, t2 UNION SELECT 1)');
    }

    public function testNestedRefusesATableNamedTwiceInANestedJoinOfAnUpdateInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'x'");

        $session->query('UPDATE (x, x UNION SELECT 1) SET a = 1');
    }

    public function testListedRefusesATableNamedTwiceBeforeAParameterMarkerInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);

        $session->query('SELECT * FROM t, t WHERE a = ?');
    }

    public function testFactorAnswersTheTablesOfATableFactor(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse('SELECT * FROM x.a AS b, (c, d.e)');
        $factors = $tree->find('table_factor');

        self::assertSame([[['x', 'b', 14]], [['db', 'c', 25], ['d', 'e', 28]]], [(new Clashes())->factor($factors[0], 'db'), (new Clashes())->factor($factors[1], 'db')]);
    }
}
