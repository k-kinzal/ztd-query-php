<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A catalog command that PostgreSQL rejects because it breaks a rule of the command.
 *
 * @visibility public
 * @example Reading the problem of a duplicated database option
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d OWNER a OWNER b');
 *     $operation->facts->diagnostics[0]->message() // => 'conflicting or redundant options'
 */
final class CatalogMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<string> The texts the message names, in the order the message names them
     */
    public readonly array $subjects;

    /**
     * @param CatalogMisuseRule $rule The broken rule
     * @param list<string> $subjects The texts the message names, one for each `%s` of the rule
     */
    public function __construct(public readonly CatalogMisuseRule $rule, array $subjects = [])
    {
        Check::input(count($subjects) === substr_count($rule->value, '%s'), 'A catalog problem names one subject for each place of its message.');
        $this->subjects = $subjects;
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return sprintf($this->rule->value, ...$this->subjects);
    }
}
