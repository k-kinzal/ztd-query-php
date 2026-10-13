<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(LoadInput::class)]
#[Medium]
final class LoadInputTest extends TestCase
{
    public function testRejectsARadixLiteralAsAFileName(): void
    {
        $this->expectExceptionMessage('A LOAD file name is quoted text.');

        new LoadInput(false, LoadSource::Infile, new Text('2', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testRejectsAFractionalFileCount(): void
    {
        $this->expectExceptionMessage('A LOAD file count is a decimal integer token no greater than 2147483647.');

        new LoadInput(false, LoadSource::Infile, new Text('f'), new Numeral('1.5'));
    }

    public function testRenderWritesTheInput(): void
    {
        self::assertSame("LOAD DATA LOCAL INFILE 'f' `count` 2 IN PRIMARY KEY ORDER INTO TABLE t", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("load data local infile 'f' count 2 in primary key order into table t")->toString());
        self::assertSame("LOAD DATA INFILE 'f' `count` 2 INTO TABLE t", (new Semantics(Dialect::MySql))->analyze("load data from infile 'f' count 2 into table t")->toString());
    }

    public function testRenderRejectsAnotherWord(): void
    {
        $this->expectExceptionMessage('The word before the number of files is COUNT.');

        new LoadInput(false, LoadSource::Infile, new Text('f'), new Numeral('2'), false, new Name('many'));
    }

    public function testCountWordNormalizesTheCanonicalKeywordSpelling(): void
    {
        $input = new LoadInput(false, LoadSource::Infile, new Text('data'), new Numeral('3'), countWord: new Name('COUNT'));

        self::assertNull($input->countWord);
        self::assertNotNull($input->count);
        self::assertSame('3', $input->count->text);
    }

}
