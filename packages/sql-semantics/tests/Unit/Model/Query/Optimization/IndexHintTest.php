<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Optimization\IndexHint;
use SqlSemantics\Model\Query\Optimization\IndexHintAction;
use SqlSemantics\Model\Query\Optimization\IndexHintScope;

#[CoversClass(IndexHint::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IndexHintTest extends TestCase
{
    public function testHintKeepsItsActionScopeAndIndexes(): void
    {
        $hint = new IndexHint(IndexHintAction::Force, IndexHintScope::Join, ['k', 'PRIMARY']);
        self::assertSame([IndexHintAction::Force, IndexHintScope::Join, ['k', 'PRIMARY']], [$hint->action, $hint->scope, $hint->indexes]);
        self::assertSame([], (new IndexHint(IndexHintAction::Use, null, []))->indexes);
    }

    /**
     * @param list<string> $indexes
     */
    #[TestWith([IndexHintAction::Ignore, []])]
    #[TestWith([IndexHintAction::Force, []])]
    #[TestWith([IndexHintAction::Use, ['']])]
    public function testHintRejectsAMissingOrEmptyIndexName(IndexHintAction $action, array $indexes): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new IndexHint($action, null, $indexes);
    }
}
