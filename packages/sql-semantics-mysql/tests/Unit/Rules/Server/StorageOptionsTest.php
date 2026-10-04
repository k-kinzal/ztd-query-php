<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(StorageOptions::class)]
#[Medium]
final class StorageOptionsTest extends TestCase
{
    public function testCheckedKeepsTheAcceptedOptions(): void
    {
        $options = [new WaitOption(true)];

        self::assertSame($options, (new StorageOptions())->checked($options, StorageOptions::DROP));
    }

    public function testDeriveReportsARepeatedOption(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TABLESPACE ts FILE_BLOCK_SIZE 4K FILE_BLOCK_SIZE 8K');

        self::assertInstanceOf(StorageProblem::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveAcceptsARepeatedNoWaitInMysql8(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('DROP TABLESPACE ts NO_WAIT NO_WAIT')->facts->diagnostics);
    }

    public function testUnsetTellsTheServersUnsetValues(): void
    {
        $options = new StorageOptions();

        self::assertTrue($options->unset(new NodegroupOption(new Numeral('65535'))));
        self::assertTrue($options->unset(new SizeOption(SizeOptionKind::FileBlock, new ByteSize(new Numeral('0')))));
        self::assertFalse($options->unset(new NodegroupOption(new Numeral('1'))));
    }

    public function testSizeReportsOverflowAndWrongWords(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLESPACE ts INITIAL_SIZE 2147483648K MAX_SIZE 3T EXTENT_SIZE 2147483647G');

        self::assertSame(['ER_SIZE_OVERFLOW_ERROR', 'ER_WRONG_SIZE_NUMBER'], array_map(static fn (Diagnostic $problem): string => $problem instanceof StorageProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }

    public function testRenderWritesTheOptionsInOrder(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' ENGINE `ndb` WAIT", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create tablespace ts add datafile 'f' engine = ndb, wait")->toString());
    }
}
