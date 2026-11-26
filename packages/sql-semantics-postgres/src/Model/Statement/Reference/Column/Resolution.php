<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column;

/**
 * The outcome of resolving a name used as a value: resolved, missing, ambiguous, conditional, or an output alias.
 *
 * Finding a candidate and resolving uniquely are different outcomes.
 *
 * @visibility SqlSemantics
 */
interface Resolution
{
}
