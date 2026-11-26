<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LoadInput::class)]
#[Medium]
final class LoadInputTest extends TestCase
{
    public function testRenderWritesTheInput(): void
    {
        self::assertSame("LOAD DATA LOCAL INFILE 'f' `count` 2 IN PRIMARY KEY ORDER INTO TABLE t", (new Inspection('mysql-8.0.44'))->inspect("load data local infile 'f' count 2 in primary key order into table t")->toString());
        self::assertSame("LOAD DATA INFILE 'f' `count` 2 INTO TABLE t", (new Inspection())->inspect("load data from infile 'f' count 2 into table t")->toString());
    }

    public function testRenderRejectsAnotherWord(): void
    {
        $this->expectExceptionMessage('The word before the number of files is COUNT.');

        new LoadInput(false, LoadSource::Infile, new Text('f'), new Numeral('2'), false, new Name('many'));
    }
}
