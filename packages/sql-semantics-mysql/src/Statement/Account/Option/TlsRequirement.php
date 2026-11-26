<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The REQUIRE clause of CREATE USER, ALTER USER or MySQL 5.x GRANT: `REQUIRE NONE | SSL | X509 | condition [AND] …`.
 *
 * The conditions are kept in source order; AND between them is optional and
 * means nothing. A property named twice is rejected by the server
 * (ER_DUP_ARGUMENT), which the statement reports.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls.
 *
 * @visibility SqlSemantics
 */
final class TlsRequirement implements Node
{
    use Snapshot;

    /**
     * @var list<TlsCondition> The conditions of a specified requirement, in order
     */
    public readonly array $conditions;

    /**
     * @param TlsKind $kind What is required
     * @param list<TlsCondition> $conditions The conditions of a specified requirement, in order; none otherwise
     */
    public function __construct(public readonly TlsKind $kind, array $conditions = [])
    {
        $this->conditions = Check::listOf($conditions, TlsCondition::class, 'A requirement lists conditions.');
        Check::input(($this->conditions !== []) === ($kind === TlsKind::Specified), 'Conditions are listed exactly by a specified requirement.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('REQUIRE');
        match ($this->kind) {
            TlsKind::None => $out->keyword('NONE'),
            TlsKind::Ssl => $out->keyword('SSL'),
            TlsKind::X509 => $out->keyword('X509'),
            TlsKind::Specified => null,
        };
        foreach ($this->conditions as $position => $condition) {
            if ($position > 0) {
                $out->keyword('AND');
            }
            $out->node($condition);
        }
    }
}
