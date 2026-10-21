<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Table;

use Fuzz\Structure\Audit\Table\Changes;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Catalog transitions retain the original requested identities and populated tables.
 */
#[CoversNothing]
#[Small]
final class ChangesTest extends TestCase
{
    /**
     * @return iterable<int, array{int}>
     */
    public static function providerSeeds(): iterable
    {
        foreach (range(0, 29) as $seed) {
            yield [$seed];
        }
    }

    /**
     * A temporary-table fixture belongs to the same session and keeps the target name.
     */
    #[DataProvider('providerSeeds')]
    public function testStepsSupplyTheRequestedTemporaryTables(int $seed): void
    {
        $choices = new Choices('temporary-catalog:' . $seed);
        $name = new QualifiedName(new Name($choices->one(['a.', "b'", 'あ']) . $seed), new Name('d'));
        $node = new M\Alter\DropTable(true, false, [new M\Alter\TargetTable($name)]);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $steps = (new Changes())->steps($node, 1051);
        self::assertInstanceOf(M\Table\CreateTable::class, $steps[1]);
        self::assertSame($name, $steps[1]->name);
        self::assertSame(1, $steps[1]->temporaryWords);
        foreach ($steps as $step) {
            $execution->runIn($session, $step);
        }
        $rows = $execution->runIn($session, new M\QueryRoot(new M\Query\ExplicitTable($name)));
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7']], $rows->rows);
        $execution->runIn($session, $node);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * A missing index is established without changing the DROP operand.
     */
    #[DataProvider('providerSeeds')]
    public function testStepsSupplyTheRequestedIndex(int $seed): void
    {
        $choices = new Choices('index-catalog:' . $seed);
        $name = new QualifiedName(new Name('t'));
        $index = new Name($choices->one(['PRIMARY', 'idx' . $seed]));
        $node = new M\Alter\DropIndex($index, $name);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $steps = (new Changes())->steps($node, 1091);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Alter\AlterTable::class, $steps[0]);
        self::assertSame($name, $steps[0]->table);
        $execution = new Execution();
        $execution->runIn($session, $steps[0]);
        $execution->runIn($session, $node);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * A rename chain starts from the needed sources without occupying intermediate names.
     */
    #[DataProvider('providerSeeds')]
    public function testRenameKeepsIntermediateNamesFree(int $seed): void
    {
        $first = new QualifiedName(new Name('a' . $seed));
        $second = new QualifiedName(new Name('b' . $seed));
        $temporary = new QualifiedName(new Name('tmp' . $seed));
        $node = new M\Alter\RenameTable([new M\Alter\TableRenaming($first, $temporary), new M\Alter\TableRenaming($second, $first), new M\Alter\TableRenaming($temporary, $second)]);
        $steps = (new Changes())->steps($node, 1017);
        self::assertCount(4, $steps);
        self::assertInstanceOf(M\Table\CreateTable::class, $steps[0]);
        self::assertInstanceOf(M\Table\CreateTable::class, $steps[2]);
        self::assertSame($first, $steps[0]->name);
        self::assertSame($second, $steps[2]->name);
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $execution = new Execution();
        foreach ($steps as $step) {
            $execution->runIn($session, $step);
        }
        $execution->runIn($session, $node);
        self::assertSame([], (new Changes())->steps($node, 1064));
    }
}
