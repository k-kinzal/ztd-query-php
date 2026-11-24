<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexTargetLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

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
}
