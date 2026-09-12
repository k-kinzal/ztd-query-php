<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\MySql\Generation\StatementRule;

/**
 * Keeps the statement argument accepted by the public Provider API.
 */
class_alias(StatementRule::class, __NAMESPACE__ . '\\StatementType');
