<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\RewriteStateCommitter;

#[CoversNothing]
final class RewriteStateCommitterTest extends TestCase
{
    public function testCommitRewriteStateDefinesSuccessfulExecutionCommitBoundary(): void
    {
        $committer = new class () implements RewriteStateCommitter {
            public bool $committed = false;

            public function commitRewriteState(): void
            {
                $this->committed = true;
            }
        };

        $committer->commitRewriteState();

        self::assertTrue($committer->committed);
    }
}
