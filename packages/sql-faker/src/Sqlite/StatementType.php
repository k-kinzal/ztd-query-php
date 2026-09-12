<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Sqlite\Generation\StatementRule;

/**
 * Keeps the statement argument accepted by the public Provider API.
 */
class_alias(StatementRule::class, __NAMESPACE__ . '\\StatementType');
