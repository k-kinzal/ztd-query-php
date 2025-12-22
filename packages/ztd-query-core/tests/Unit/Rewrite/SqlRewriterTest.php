<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlRewriter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SqlRewriterTest extends TestCase
{
    public function testFakeImplementsInterface(): void
    {
        $rewriter = new FakeSqlRewriter(new ShadowStore(), new TableDefinitionRegistry());

        self::assertInstanceOf(SqlRewriter::class, $rewriter);
    }
}
