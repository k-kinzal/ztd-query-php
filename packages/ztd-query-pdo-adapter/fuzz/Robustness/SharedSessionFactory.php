<?php

declare(strict_types=1);

namespace Fuzz\Robustness;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;

/**
 * Gives the adapter and the invariant checkers the same shadow state.
 */
final class SharedSessionFactory implements SessionFactory
{
    /**
     * Share the rewriter and shadow state with the invariant checkers.
     */
    public function __construct(
        private readonly SqlRewriter $rewriter,
        private readonly ShadowStore $shadowStore,
        private readonly TableDefinitionRegistry $registry,
    ) {
    }

    /**
     * Create an adapter session using the monitored shadow state.
     */
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        return new Session(
            $this->rewriter,
            $this->shadowStore,
            new ResultSelectRunner(),
            $config,
            $connection,
            new ShadowTransactionManager($this->shadowStore, $this->registry),
            $this->registry,
            resultColumnTypeResolver: new MySqlResultColumnTypeResolver(),
        );
    }
}
