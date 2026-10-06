<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption;

#[CoversClass(CommentOption::class)]
#[Medium]
final class CommentOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' COMMENT 'c'", (new Semantics(Dialect::MySql))->analyze("create tablespace ts add datafile 'f' comment = 'c'")->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('COMMENT', (new CommentOption(new Text('c')))->keyword());
    }
}
