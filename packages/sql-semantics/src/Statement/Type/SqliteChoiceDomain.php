<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Declaration\TypeDescriptor;

/**
 * The possible value domains selected by SQLite branches, without a common coercion.
 * Branch declaration affinities are not an affinity of the selection expression.
 * @visibility public
 * @example Retaining the reason a branch's value type cannot be determined
 *     (new \SqlSemantics\Statement\Type\SqliteChoiceDomain(\SqlSemantics\Statement\Type\Unresolved::MissingDeclaration, \SqlSemantics\Statement\Type\NullDomain::Null))->alternatives // => [\SqlSemantics\Statement\Type\Unresolved::MissingDeclaration, \SqlSemantics\Statement\Type\NullDomain::Null]
 */
final class SqliteChoiceDomain
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<TypeDescriptor|NullDomain|Unresolved|SqliteNumericDomain>
     */
    public readonly array $alternatives;

    /**
     * Flattens nested choices while retaining the original declaration type objects.
     */
    public function __construct(TypeDescriptor|NullDomain|Unresolved|SqliteNumericDomain|self ...$alternatives)
    {
        \SqlSemantics\Statement\Validation\Check::input($alternatives !== [], 'A value selection has at least one possible result domain.');
        $domains = [];
        foreach ($alternatives as $alternative) {
            foreach ($alternative instanceof self ? $alternative->alternatives : [$alternative] as $domain) {
                if (!in_array($domain, $domains, true)) {
                    $domains[] = $domain;
                }
            }
        }
        $this->alternatives = $domains;
    }
}
