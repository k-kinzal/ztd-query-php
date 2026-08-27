<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Override;
use PDO;
use PDOStatement;

/**
 * A connection that writes down the statements it is asked for.
 *
 * A catalog's job is to ask the server the right question, and the question is
 * the statement it prepares. No SQLite connection can answer a PostgreSQL
 * catalog query, so this records what was asked and hands back a statement over
 * the same placeholders that answers nothing.
 */
final class RecordingPdo extends PDO
{
    /**
     * @var list<string> Statements this was asked to prepare, in order
     */
    public array $prepared = [];

    /**
     * @var list<string> Statements this was asked to run, in order
     */
    public array $queried = [];

    /**
     * Opens a connection with nothing in it.
     */
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $options Ignored
     *
     * @return PDOStatement|false A statement over the same placeholders, answering nothing
     */
    #[Override]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepared[] = $query;

        return parent::prepare($this->answeringNothing($query));
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed ...$fetchModeArgs Ignored
     *
     * @return PDOStatement|false A statement answering nothing
     */
    #[Override]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queried[] = $query;

        return parent::query('SELECT 1 WHERE 0');
    }

    /**
     * Answers a statement that takes the same placeholders and answers no rows.
     *
     * @param string $query Statement as the caller wrote it
     *
     * @return string A statement over the same placeholders
     */
    public function answeringNothing(string $query): string
    {
        preg_match_all('/:(\w+)/', $query, $matches);
        $placeholders = array_values(array_unique($matches[1]));
        if ($placeholders === []) {
            return 'SELECT 1 WHERE 0';
        }

        return 'SELECT ' . implode(', ', array_map(static fn (string $name): string => ':' . $name, $placeholders)) . ' WHERE 0';
    }
}
