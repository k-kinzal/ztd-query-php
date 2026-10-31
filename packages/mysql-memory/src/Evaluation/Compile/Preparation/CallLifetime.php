<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Constancy;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;

/**
 * Classifies named calls from their semantic native identity and release.
 * Argument lifetimes are combined separately by the expression walker.
 *
 * @visibility MySqlMemory
 */
final class CallLifetime
{
    /**
     * Classifies a named call before combining the lifetime of its arguments.
     * Semantic native identity distinguishes built-ins from qualified or quoted routines.
     */
    public static function of(FunctionCall $node, bool $legacy = false, ?GrammarRelease $release = null): Constancy
    {
        if (!$node->native($release ?? ($legacy ? GrammarRelease::MySql5744 : GrammarRelease::MySql847))) {
            return Constancy::Row;
        }
        $name = strtoupper($node->name->value);
        return $legacy && in_array($name, Constancy::ACCOUNT_FUNCTIONS, true) ? Constancy::Resolved : Constancy::function($name, count($node->arguments));
    }
}
