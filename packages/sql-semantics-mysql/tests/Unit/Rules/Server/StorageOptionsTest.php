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
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption;

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
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        (new Semantics(Dialect::MySql))->analyze('CREATE TABLESPACE ts FILE_BLOCK_SIZE 4K FILE_BLOCK_SIZE 8K');
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

    public function testRepeatedKeepsUnsetSentinelsAndWaitTransitions(): void
    {
        $rule = new StorageOptions();

        self::assertNull($rule->repeated([new NodegroupOption(new Numeral('65535')), new NodegroupOption(new Numeral('1'))]));
        self::assertSame('NODEGROUP', $rule->repeated([new NodegroupOption(new Numeral('1')), new NodegroupOption(new Numeral('65535'))])?->option);
        self::assertNull($rule->repeated([new SizeOption(SizeOptionKind::FileBlock, new ByteSize(new Numeral('0'))), new SizeOption(SizeOptionKind::FileBlock, new ByteSize(new Numeral('4096')))]));
        self::assertNull($rule->repeated([new WaitOption(false), new WaitOption(false)]));
        self::assertSame('NO_WAIT', $rule->repeated([new WaitOption(false), new WaitOption(false)], true)?->option);
        self::assertNull($rule->repeated([new WaitOption(false), new WaitOption(true), new WaitOption(false)], true));
    }

    public function testSizeAcceptsTheLargestConstructibleUnitCoefficient(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLESPACE ts INITIAL_SIZE 2147483647K MAX_SIZE 0 EXTENT_SIZE 2147483647G');

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheOptionsInOrder(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' ENGINE ndb WAIT", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create tablespace ts add datafile 'f' engine = ndb, wait")->toString());
    }
}
