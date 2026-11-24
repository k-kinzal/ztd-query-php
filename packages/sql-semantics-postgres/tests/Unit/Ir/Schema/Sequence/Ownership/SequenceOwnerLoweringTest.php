<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceOwnerLowering::class)]
final class SequenceOwnerLoweringTest extends TestCase
{
    public function testLowerUsesTheSameOwnerForBareAndQualifiedNames(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.owner (n integer, label text) PARTITION BY RANGE(n)']);
        $bare = $semantics->analyze('CREATE SEQUENCE counter OWNED BY owner.label', $context);
        $qualified = $semantics->analyze('CREATE SEQUENCE public.counter OWNED BY public.owner.label', $context);

        self::assertInstanceOf(CreateSequence::class, $bare);
        self::assertInstanceOf(CreateSequence::class, $qualified);
        self::assertEquals($bare->owner, $qualified->owner);
        self::assertSame(SequenceOwnerKind::PartitionedTable, $bare->owner?->kind);
    }

    public function testFindRequiresADefiniteDeclaredRelation(): void
    {
        $semantics = new Semantics();
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence ownership requires a definite declared relation.');

        $semantics->analyze('CREATE SEQUENCE public.counter OWNED BY public.unknown.n', $semantics->context([]));
    }

    public function testCheckIndexesDoesNotSkipAShadowingIndex(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('front', 'behind', 'public'));
        $context = $semantics->context([
            'CREATE TABLE front.source (n integer)',
            'CREATE INDEX occupying ON front.source (n)',
            'CREATE TABLE behind.occupying (n integer)',
        ]);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence ownership requires an eligible table-like relation.');

        $semantics->analyze('CREATE SEQUENCE behind.counter OWNED BY occupying.n', $context);
    }
}
