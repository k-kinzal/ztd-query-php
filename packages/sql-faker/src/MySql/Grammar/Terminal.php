<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

/**
 * Preserves the former MySQL symbol name for callers of the compatibility grammar facade.
 */
class_alias(\SqlFaker\Grammar\Terminal::class, __NAMESPACE__ . '\\Terminal');
