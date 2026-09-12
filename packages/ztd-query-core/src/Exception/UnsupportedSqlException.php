<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use Throwable;

/**
 * Refuses a statement ZTD cannot simulate.
 *
 * ZTD never writes to the database, so a statement it cannot work out the
 * effect of is refused rather than run. The statement is carried whole,
 * because what a caller needs in order to act on this is which statement was
 * refused, not only that one was.
 */
final class UnsupportedSqlException extends SimulationException
{
    /** @var string The statement being refused, as written */
    private string $sql;

    /** @var string What about it ZTD cannot simulate */
    private string $category;

    /**
     * @param string $sql The statement being refused, as written
     * @param string $category What about it ZTD cannot simulate
     * @param Throwable|null $previous What made this impossible, where something did
     */
    public function __construct(string $sql, string $category = 'Unsupported', ?Throwable $previous = null)
    {
        parent::__construct(sprintf('ZTD Write Protection: %s SQL statement.', $category), 0, $previous);
        $this->sql = $sql;
        $this->category = $category;
    }

    /**
     * Answers the statement being refused.
     *
     * @return string The statement, as written
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Answers what about the statement ZTD cannot simulate.
     *
     * @return string What was refused
     */
    public function getCategory(): string
    {
        return $this->category;
    }
}
