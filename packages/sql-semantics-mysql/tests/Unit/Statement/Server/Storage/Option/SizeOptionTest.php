<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;

#[CoversClass(SizeOption::class)]
#[Medium]
final class SizeOptionTest extends TestCase
{
    public function testRenderWritesKeywordAndSize(): void
    {
        self::assertSame('ALTER TABLESPACE ts AUTOEXTEND_SIZE `4M` MAX_SIZE 1024', (new Semantics(Dialect::MySql))->analyze('alter tablespace ts autoextend_size = 4M max_size 1024')->toString());
    }

    public function testKeywordAnswersTheKind(): void
    {
        self::assertSame('EXTENT_SIZE', (new SizeOption(SizeOptionKind::Extent, new ByteSize(new Numeral('1'))))->keyword());
    }
}
