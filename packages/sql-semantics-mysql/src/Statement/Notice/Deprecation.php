<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Snapshot;

/**
 * The warning the server raises for a deprecated or converted construct while it reads a statement.
 *
 * Rule: MYSQL-DEPRECATION-001. The server raises the warning of a construct
 * when it parses the construct, so the warnings come before any problem of
 * the statement and in the order the constructs are written; a statement
 * that fails keeps them. Each construct warns once per occurrence, in the
 * releases that deprecate it (Deprecated::warnedIn()). Terminates: no
 * recursion. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the warning of BINARY
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT BINARY 1');
 *     [$query->facts->warnings[0]->construct, $query->facts->warnings[0]->code()] // => [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator, 1287]
 */
final class Deprecation implements Warning
{
    use Snapshot;

    /**
     * @param Deprecated $construct The construct warned about
     */
    public function __construct(public readonly Deprecated $construct)
    {
    }

    /**
     * Records the warning of a construct when the release of the derivation warns about it.
     */
    public static function raise(Deprecated $construct, Derivation $derivation): void
    {
        if ($construct->warnedIn($derivation->context->profile->grammar)) {
            $derivation->warn(new self($construct));
        }
    }

    /**
     * Answers the error number of the warning.
     */
    public function code(): int
    {
        return $this->construct->code();
    }

    /**
     * Answers the text of the warning.
     */
    public function message(): string
    {
        return $this->construct->value;
    }
}
