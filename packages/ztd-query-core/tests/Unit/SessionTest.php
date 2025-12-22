<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakeSqlRewriter;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(Session::class)]
#[UsesClass(ZtdConfig::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ResultSelectRunner::class)]
final class SessionTest extends TestCase
{
    public function testEnableAndDisable(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $rewriter = new FakeSqlRewriter($shadowStore, $registry);
        $connection = new FakeConnection();
        $session = new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection,
        );

        self::assertTrue($session->isEnabled());

        $session->disable();
        self::assertFalse($session->isEnabled());

        $session->enable();
        self::assertTrue($session->isEnabled());
    }
}
