<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

/**
 * A session factory that answers with a session and remembers being asked.
 *
 * A test that needs to know the adapter opened a session -- once, never, or
 * with a particular connection and configuration -- can read that off the
 * recorded calls afterwards. A mock would answer the same question through
 * expectations set before the run, which PHPUnit 13 requires to be sealed and
 * the older majors this package still supports cannot seal.
 */
final class RecordingSessionFactory implements SessionFactory
{
    /**
     * @var list<array{ConnectionInterface, ZtdConfig}> Every call, in the order it arrived
     */
    private array $calls = [];

    /**
     * Binds the factory to what it answers with.
     *
     * @param Closure(ConnectionInterface, ZtdConfig): Session $answer What to answer for a given connection and configuration
     */
    public function __construct(private readonly Closure $answer)
    {
    }

    /**
     * Answers a factory that builds a plain session around the given rewriter.
     *
     * @param SqlRewriter $rewriter Rewriter the answered session runs its SQL through
     *
     * @return self The factory
     */
    public static function answeringWith(SqlRewriter $rewriter): self
    {
        return new self(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
            $rewriter,
            new ShadowStore(),
            new ResultSelectRunner(),
            $config,
            $connection,
        ));
    }

    /**
     * Answers a session, and remembers what it was asked for.
     *
     * @param ConnectionInterface $connection Connection the session runs on
     * @param ZtdConfig $config Configuration the session was asked for
     *
     * @return Session The session
     */
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        $this->calls[] = [$connection, $config];

        return ($this->answer)($connection, $config);
    }

    /**
     * Answers every call this factory received.
     *
     * @return list<array{ConnectionInterface, ZtdConfig}> The connection and configuration of each call
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
