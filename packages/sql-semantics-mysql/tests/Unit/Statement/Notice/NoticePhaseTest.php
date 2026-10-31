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
        $semantics = new Semantics(Dialect::MySql);
        $expression = $semantics->analyze('SELECT @v COLLATE utf8mb4_bin')->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $expression);
        $expression = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($expression);
        $derivation = new \SqlSemantics\Construction\Derivation($semantics->context());
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->derive($expression, $derivation, $derivation->environment());

        $notices = array_values(array_filter($derivation->facts()->warnings, static fn ($notice): bool => $notice instanceof ParseFailure));

        self::assertCount(1, $notices);
        self::assertSame([NoticePhase::Contextualization], array_column($notices, 'phase'));
        self::assertNotSame(NoticePhase::Syntax, NoticePhase::Contextualization);
    }
}
