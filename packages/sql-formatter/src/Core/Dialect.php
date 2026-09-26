<?php

declare(strict_types=1);

namespace SqlFormatter\Core;

use SqlFormatter\Core\Compact\Settings;
use SqlFormatter\Core\Syntax\Rules;

/**
 * Supplies grammar and lexical behavior to the formatting engine.
 *
 * @visibility SqlFormatter
 */
interface Dialect
{
    /**
     * Declares grammar roles used by the layout annotator.
     */
    public function syntaxRules(): Rules;

    /**
     * Declares canonicalization and lexical behavior for compact output.
     */
    public function compactRules(): Settings;
}
