<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

use Fiber;
use FiberError;

/**
 * Lets a statement wait for a row lock while the server goes on serving the other connections.
 *
 * The listener runs the work of each connection in a fiber it admits here. A statement that must
 * wait suspends its fiber, and the listener resumes it from time to time, when it checks again
 * for the lock. Code that runs outside such a fiber, as a session used in process does, cannot
 * wait: nothing else can run while it would.
 * Source: https://www.php.net/manual/en/language.fibers.php.
 *
 * @visibility MySqlMemory
 */
final class Scheduler
{
    /**
     * @var array<int, true> The object ids of the fibers that may wait
     */
    public array $fibers = [];

    /**
     * Admits a fiber whose work may wait.
     */
    public function admit(object $fiber): void
    {
        $this->fibers[spl_object_id($fiber)] = true;
    }

    /**
     * Forgets a fiber.
     */
    public function dismiss(object $fiber): void
    {
        unset($this->fibers[spl_object_id($fiber)]);
    }

    /**
     * Tells whether the running code can wait: it runs in an admitted fiber.
     */
    public function suspendable(): bool
    {
        $fiber = Fiber::getCurrent();

        return $fiber !== null && isset($this->fibers[spl_object_id($fiber)]);
    }

    /**
     * Suspends the running fiber until the listener resumes it.
     *
     * @throws FiberError When the running code is not a fiber
     */
    public function pause(): void
    {
        Fiber::suspend();
    }
}
