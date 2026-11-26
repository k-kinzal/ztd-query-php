<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ConstraintIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexTargetLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclarationKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(IndexTargetLowering::class)]
#[Small]
final class IndexTargetLoweringTest extends TestCase
{
    public function testResolveKeepsUninitializedTemporaryNamespaceDistinct(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::PostgreSql172), [new Name('pg_temp'), new Name('public')]);
        $selector = new QualifiedName(new Name('outside'), new Name('pg_temp'));
        $target = (new IndexTargetLowering())->resolve($context, $selector, true);

        self::assertInstanceOf(MissingIndex::class, $target);
        self::assertTrue($target->missingSchema);
        self::assertSame('"pg_temp"."outside"', $target->quoted());
    }

    public function testLookupReturnsTheExactDeclaredPhysicalKind(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE public.orders (id integer, CONSTRAINT selected PRIMARY KEY (id))']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $name = new QualifiedName(new Name('selected'), new Name('public'));

        self::assertSame(IndexDeclarationKind::KeyConstraint, (new IndexTargetLowering())->lookup($context, $name)?->kind);
    }

    public function testResolveRebuildUsesTheNamedConstraintRole(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE public.orders (id bigint, CONSTRAINT selected UNIQUE (id))']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $name = new QualifiedName(new Name('selected'), new Name('public'));
        $index = (new IndexTargetLowering())->resolveRebuild($context, $name);

        self::assertInstanceOf(ConstraintIndexReference::class, $index);
        self::assertSame('orders', $index->table->value);
    }
}
