<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `FOR SEARCH` or `FOR ORDER BY sort_family`: what an operator of an operator class is for.
 *
 * Without the clause an operator is for searching, as with FOR SEARCH; the
 * written clause is kept. An ordering operator names the btree operator
 * family that sorts its results.
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html.
 *
 * @visibility public
 * @example Telling a search operator
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberPurpose())->sortFamily // => null
 */
final class MemberPurpose implements Clause
{
    use Snapshot;

    /**
     * @param DottedName|null $sortFamily The sort operator family of an ordering operator; null for FOR SEARCH
     */
    public function __construct(public readonly ?DottedName $sortFamily = null)
    {
    }

    /**
     * Derives nothing: a name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes FOR SEARCH or FOR ORDER BY and the family.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR');
        if ($this->sortFamily === null) {
            $out->keyword('SEARCH');

            return;
        }
        $out->keyword('ORDER', 'BY')->node($this->sortFamily);
    }
}
