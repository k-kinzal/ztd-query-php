<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Override;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Creates real sessions while recording the adapter's factory arguments.
 */
final class RecordingSessionFactory implements SessionFactory
{
    /**
     * Connection received from the adapter.
     */
    public ?ConnectionInterface $connection = null;

    /**
     * Configuration received from the adapter.
     */
    public ?ZtdConfig $config = null;

    /**
     * Number of sessions requested.
     */
    public int $calls = 0;

    /**
     * Supply the rewrite behavior and shadow state visible to the test.
     */
    public function __construct(private SqlRewriter $rewriter, private ShadowStore $store)
    {
    }

    /**
     * Build a session with the provided connection and record its configuration.
     */
    #[Override]
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->calls++;

        return new Session($this->rewriter, $this->store, new ResultSelectRunner(), $config, $connection);
    }
}
