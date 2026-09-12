<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Builds sessions over fakes, for tests that are about the session itself.
 *
 * A session takes five things before it will answer anything, and a test
 * asking what one of its methods does has no interest in four of them.
 */
final class SessionUnderTest
{
    /**
     * Answers a session over an empty shadow.
     *
     * @return Session The session
     */
    public static function plain(): Session
    {
        return self::over(new ShadowStore());
    }

    /**
     * Answers a session over a shadow the caller has already filled.
     *
     * @param ShadowStore $store Shadow the session writes into
     *
     * @return Session The session
     */
    public static function over(ShadowStore $store): Session
    {
        $registry = new TableDefinitionRegistry();

        return new Session(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );
    }
}
