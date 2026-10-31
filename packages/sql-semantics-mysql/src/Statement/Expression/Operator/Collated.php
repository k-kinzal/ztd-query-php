<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A value with an explicit collation: `x COLLATE collation` (`Item_func_set_collation`).
 *
 * COLLATE is the tightest operator; a chain of COLLATE clauses applies
 * from the left (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-COLLATE-001. Facts: the type of the operand; its NULL fact in
 * MySQL 5.6, otherwise nullable, including for a constant stored by CREATE TABLE AS SELECT.
 * the collation is part of the comparison semantics the server applies, and
 * the character set of the operand must admit it, which needs the
 * collation catalog of the server and is not checked. A collation the
 * server does not know is resolved during preparation in MySQL 5.6 and 5.7,
 * after operand dependencies. Later releases look up a nonempty name during
 * parsing; its ParseFailure notice follows the operand warnings. An empty
 * name is resolved after the operand (verified on live legacy and modern servers).
 * MySQL 5.6 and 5.7 defer expression collation lookup in stored instructions
 * until invocation; the retained name is resolved when that instruction is derived.
 * Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collate.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the collation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a COLLATE utf8mb4_bin = 'x'");
 *     $query->query->where->left->collation->value // => 'utf8mb4_bin'
 */
final class Collated implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $operand The collated expression
     * @param Name $collation The collation name
     */
    public function __construct(public readonly Scalar $operand, public readonly Name $collation, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new Precedence())->fits($operand, Precedence::COLLATION, Precedence::SIMPLE_EXPR), 'The operand of COLLATE needs a grouping to keep its place.');
        Check::input(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($collation->value) !== null, 'A collation name is one the server knows.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand; the collated value has its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $known = Collation::forName($this->collation->value, $derivation->context->profile->grammar) !== null;
        $fact = (new Operands())->single((new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CollationLookup())->derive($this, $derivation, $environment), $derivation);
        $operand = (new Precision())->domain($fact->type);
        $domain = $operand === null || !$known ? null : (new Texts(Settings::of($derivation->context)))->collated($operand, $this->collation->value, $derivation, $this);

        $legacy = $derivation->context->profile->grammar === GrammarRelease::MySql5651;

        return new ScalarFact($domain === null ? $fact->type : new Known($domain), $legacy ? $fact->nullability : Nullability::Nullable);
    }

    /**
     * Writes the operand, COLLATE and the collation name.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('COLLATE')->name($this->collation, NameUse::Label);
    }
}
