<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Psr\Log\LoggerInterface;
use Testcontainers\Containers\ContainerInstance;
use Testcontainers\Containers\WaitStrategy\WaitStrategy;
use Testcontainers\Testcontainers;

/**
 * Starts a second independent server while the first server initializes.
 * Both retain the repository's SQL readiness checks. The first container is
 * already running when this strategy starts the companion; neither server is
 * published as ready until its ordinary readiness check has completed.
 */
final class CompanionStartup implements WaitStrategy
{
    /**
     * Connects the owning definition to its independently owned companion instance.
     */
    public function __construct(private readonly DisposableMySql $owner, private readonly DisposableMySql $companion, private readonly WaitStrategy $readiness)
    {
    }

    /**
     * Starts the companion and then finishes the first server's SQL readiness check.
     *
     * @param ContainerInstance $instance The first container, already running
     */
    #[Override]
    public function waitUntilReady($instance): void
    {
        $this->owner->companionInstance = Testcontainers::run($this->companion);
        $this->readiness->waitUntilReady($instance);
    }

    /**
     * Retains the repository's readiness logging.
     *
     * @param LoggerInterface $logger The container logger
     */
    #[Override]
    public function withLogger($logger): self
    {
        $this->readiness->withLogger($logger);
        return $this;
    }
}
