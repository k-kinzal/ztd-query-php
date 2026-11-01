<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Definition\TemporaryStorage;
use MySqlMemory\Program\Expression\TableUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TemporaryStorage::class)]
#[Medium]
final class TemporaryStorageTest extends TestCase
{
    public function testRaiseRetainsTheSameConflictAcrossDestinationStatesWithoutInputText(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $option = new M\Table\Option\AutoextendSizeOption(new M\Literal\ByteSize(new M\Literal\Numeral('1')));
        $definition = new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column], [$option], temporaryWords: 1, ifNotExists: true);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $observe = static function () use ($definition, $session): int {
            $session->text = 'unrelated invalid input';
            try {
                (new TableUse())->raise($definition, $session);
                return 0;
            } catch (SqlError $error) {
                return $error->getCode();
            }
        };
        self::assertSame(4026, $observe());
        $session->query('CREATE TEMPORARY TABLE t(a INT)');
        $session->query('INSERT INTO t VALUES(7)');
        self::assertSame(4026, $observe());
        self::assertSame('unrelated invalid input', $session->text);
        self::assertNull((new TableUse())->prepare($definition, $session));
        $rows = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7']], $rows->rows);
    }
    public function testStartRepeatsNoticesOnlyAfterTheInitialStoredPreparation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE PROCEDURE p() CREATE TEMPORARY TABLE t(a NATIONAL CHAR(2),b INT(11)) AUTOEXTEND_SIZE=1');
        $observe = static function () use ($session): array {
            $session->run('CALL p()');
            return array_column($session->diagnostics->conditions, 1);
        };
        self::assertSame([3720, 4026], $observe());
        self::assertSame([3720, 1681, 4026], $observe());
        $session->query("ALTER PROCEDURE p COMMENT 'changed'");
        self::assertSame([3720, 4026], $observe());
    }

}
