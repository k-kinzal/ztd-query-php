<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat;

#[CoversClass(TextFileFormat::class)]
#[Medium]
final class TextFileFormatTest extends TestCase
{
    public function testRenderWritesCharsetFieldsAndLines(): void
    {
        self::assertSame("SELECT 1 INTO OUTFILE 'f' CHARSET DEFAULT COLUMNS ESCAPED BY 'e' TERMINATED BY ',' LINES TERMINATED BY ';'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("select 1 into outfile 'f' character set default fields escaped by 'e' terminated by ',' lines terminated by ';'")->toString());
    }
}
