<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\Model\CallContext;
use SqlCatalog\Core\Extension\Model\ModelSet;
use SqlCatalog\Core\Extension\Model\QueryModelInterface;

#[CoversClass(ModelSet::class)]
final class ModelSetTest extends TestCase
{
    public function testMergePreservesCallOrderAndReplacesStatementKeys(): void
    {
        $first = self::createStub(QueryModelInterface::class);
        $second = self::createStub(QueryModelInterface::class);
        $call = static fn (CallContext $context): ?Domain => null;
        $relation = static fn (string $class, string $expected): bool => $class === 'Concrete' && $expected === 'Contract';
        $merged = (new ModelSet([$call], ['first' => $first, 'shared' => $first]))->merge(new ModelSet([$call], ['shared' => $second], [$relation]));
        self::assertSame([$call, $call], $merged->calls);
        self::assertSame(['first' => $first, 'shared' => $second], $merged->queries);
        self::assertTrue($merged->matchesClass('Concrete', 'Contract'));
    }

    public function testMatchesClassFallsThroughRelationsWithoutClaimingUnknownTypes(): void
    {
        $models = new ModelSet(classRelations: [static fn (string $class, string $expected): bool => false, static fn (string $class, string $expected): bool => $class === 'Known' && $expected === 'Base']);
        self::assertTrue($models->matchesClass('Known', 'Base'));
        self::assertFalse($models->matchesClass('Other', 'Base'));
        self::assertFalse((new ModelSet())->matchesClass('Known', 'Base'));
    }
}
