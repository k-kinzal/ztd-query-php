<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(NoticePhase::class)]
#[Medium]
final class NoticePhaseTest extends TestCase
{
    public function testCasesDistinguishTheInnerOperandFromItsPartitionContext(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SHOW PARSE_TREE CREATE TABLE t (a INT) PARTITION BY HASH(@v COLLATE missing)');

        $notices = array_values(array_filter($operation->facts->warnings, static fn ($notice): bool => $notice instanceof ParseFailure));

        self::assertCount(2, $notices);
        self::assertSame([NoticePhase::Syntax, NoticePhase::Contextualization], array_column($notices, 'phase'));
    }
}
