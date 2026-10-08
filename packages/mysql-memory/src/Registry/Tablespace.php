<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * A general or undo tablespace of InnoDB that a statement created.
 *
 * An undo tablespace is active when it is created; ALTER UNDO TABLESPACE ... SET INACTIVE makes
 * it inactive, which the emulator treats as already truncated, so that it can be dropped.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/general-tablespaces.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-undo-tablespaces.html.
 *
 * @visibility MySqlMemory
 */
final class Tablespace
{
    /**
     * @param string $name The tablespace name, which compares with regard to letter case
     * @param bool $undo Whether it is an undo tablespace
     * @param string $file The name of its data file
     * @param bool $active Whether an undo tablespace is active
     */
    public function __construct(public string $name, public readonly bool $undo, public readonly string $file, public bool $active = true)
    {
    }
}
