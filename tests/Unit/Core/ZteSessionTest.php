<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\QueryGuard;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\ZteSession;
use PHPUnit\Framework\TestCase;

final class ZteSessionTest extends TestCase
{
    public function testEnableDisableAndRewriteDelegation(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $rewriter = new RecordingRewriter();
        $shadowApplier = new ShadowApplier($shadowStore);
        $runner = new ResultSelectRunner();

        $session = new ZteSession($shadowStore, $schemaRegistry, $guard, $rewriter, $shadowApplier, $runner, ZtdConfig::default());

        $this->assertFalse($session->isEnabled());
        $session->enable();
        $this->assertTrue($session->isEnabled());
        $session->disable();
        $this->assertFalse($session->isEnabled());

        $plan = $session->rewrite('SELECT 1');
        $this->assertSame('SELECT 1', $plan->sql());
        $this->assertSame(QueryKind::READ, $plan->kind());
        $this->assertSame(['SELECT 1'], $rewriter->calls);

        $this->assertSame($shadowStore, $session->shadowStore());
        $this->assertSame($schemaRegistry, $session->schemaRegistry());
        $this->assertSame($guard, $session->guard());
        $this->assertSame($rewriter, $session->rewriter());
        $this->assertSame($shadowApplier, $session->shadowApplier());
        $this->assertSame($runner, $session->resultSelectRunner());
    }
}

final class RecordingRewriter implements SqlRewriter
{
    /**
     * Captured SQL inputs passed to rewrite().
     *
     * @var array<int, string>
     */
    public array $calls = [];

    public function rewrite(string $sql): RewritePlan
    {
        $this->calls[] = $sql;
        return new RewritePlan($sql, QueryKind::READ);
    }

    public function rewriteMultiple(string $sql): \ZtdQuery\Rewrite\MultiRewritePlan
    {
        $this->calls[] = $sql;
        return new \ZtdQuery\Rewrite\MultiRewritePlan([new RewritePlan($sql, QueryKind::READ)]);
    }
}
