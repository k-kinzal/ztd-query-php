<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An ODBC escape: `{ kind expr }` (`PTI_odbc_date`).
 *
 * With the kind `d`, `t` or `ts` (in any letter case) and a string literal,
 * the server reads a DATE, TIME or DATETIME literal; with any other kind or
 * operand the escape stands for its operand.
 *
 * Rule: MYSQL-ODBC-ESCAPE-001. Facts: the temporal literal type and never
 * NULL for the temporal kinds over a string literal; otherwise the facts of
 * the operand. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing an ODBC date
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = { d '2024-01-31' }");
 *     $query->facts->scalar($query->statement->where->right)->type->descriptor->name() // => 'DATE'
 */
final class OdbcEscape implements Scalar
{
    use Snapshot;

    /**
     * The temporal kinds of the escape keywords.
     */
    private const KINDS = ['d' => TemporalKind::Date, 't' => TemporalKind::Time, 'ts' => TemporalKind::DateTime];

    /**
     * @param Name $kind The escape keyword
     * @param Scalar $operand The escaped expression
     */
    public function __construct(public readonly Name $kind, public readonly Scalar $operand)
    {
    }

    /**
     * Derives the operand and, for a temporal escape over a string, the temporal type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        $kind = self::KINDS[strtolower($this->kind->value)] ?? null;
        if ($kind !== null && $this->operand instanceof StringLiteral) {
            return new ScalarFact(new Known(new Temporal($kind)), Nullability::NotNull);
        }

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the braces around the kind and the operand.
     */
    public function render(Output $out): void
    {
        $out->symbol('{')->name($this->kind, NameUse::Label)->node($this->operand)->symbol('}');
    }
}
