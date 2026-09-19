<?php

declare(strict_types=1);

namespace SqlParser\Resource;

/**
 * One supported release of a dialect and the resources shipped for it.
 *
 * @visibility root
 */
final class SqlVersion
{
    /**
     * @param string $dialect Dialect the release belongs to
     * @param string $name Release tag, such as `mysql-8.4.7`
     * @param string $tablePath Absolute path of the parse table
     * @param string $keywordPath Absolute path of the keyword table
     */
    public function __construct(
        public readonly string $dialect,
        public readonly string $name,
        public readonly string $tablePath,
        public readonly string $keywordPath,
    ) {
    }
}
