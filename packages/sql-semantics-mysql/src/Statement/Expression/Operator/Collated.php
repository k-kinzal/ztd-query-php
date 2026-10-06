<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A value with an explicit collation: `x COLLATE collation` (`Item_func_set_collation`).
 *
 * COLLATE is the tightest operator; a chain of COLLATE clauses applies
 * from the left (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-COLLATE-001. Facts: the type and NULL fact of the operand;
 * the collation is part of the comparison semantics the server applies, and
 * the character set of the operand must admit it, which needs the
 * collation catalog of the server and is not checked. Terminates: the
 * operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collate.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the collation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a COLLATE utf8mb4_bin = 'x'");
 *     $query->statement->where->left->collation->value // => 'utf8mb4_bin'
 */
final class Collated implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The collated expression
     * @param Name $collation The collation name
     */
    public function __construct(public readonly Scalar $operand, public readonly Name $collation)
    {
        Check::input((new Precedence())->fits($operand, Precedence::COLLATION, Precedence::SIMPLE_EXPR), 'The operand of COLLATE needs a grouping to keep its place.');
    }

    /**
     * Derives the operand; the collated value has its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the operand, COLLATE and the collation name.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('COLLATE')->name($this->collation, NameUse::Label);
    }
}
