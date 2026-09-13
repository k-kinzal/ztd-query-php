<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * Failures extracting a table schema from SQL.
 */
abstract class SchemaParseException extends RuntimeException
{
}
