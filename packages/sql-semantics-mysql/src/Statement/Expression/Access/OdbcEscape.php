<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An ODBC escape: `{ kind expr }` (`PTI_odbc_date`).
 *
 * With the kind `d`, `t` or `ts` (in lower case) and a string literal, the
 * server reads a DATE, TIME or TIMESTAMP literal; with any other kind or
 * operand the escape stands for its operand, and names its column as the
 * operand does. A string that is no value of the kind stands for itself too,
 * which is checked before constructing a temporal value (verified on a live 8.4 server).
 *
 * Rule: MYSQL-ODBC-ESCAPE-001. Facts: the type of the temporal literal and
 * never NULL for the temporal kinds over a string literal; otherwise the
 * facts of the operand. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing an ODBC date
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = { d '2024-01-31' }");
 *     $query->query->where->right->scalarInformation()->type->descriptor->name() // => 'DATE'
 */
final class OdbcEscape implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The temporal literal forms of the escape keywords.
     */
    private const FORMS = ['d' => TemporalForm::Date, 't' => TemporalForm::Time, 'ts' => TemporalForm::Timestamp];

    /**
     * @param Name $kind The escape keyword
     * @param Scalar $operand The escaped expression
     */
    public function __construct(public readonly Name $kind, public readonly Scalar $operand, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers the temporal literal the escape reads, or null when it stands for its operand.
     * Without settings this answers potential interpretation under a permissive calendar mode.
     *
     * @param \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings|null $settings Calendar settings when resolving in a known session
     *
     * @example A date escape
     *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape(new \SqlSemantics\Statement\Identifier\Name('d'), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['2024-01-31'])))->literal()?->form // => \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date
     */
    public function literal(?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings $settings = null): ?TemporalLiteral
    {
        $form = self::FORMS[$this->kind->value] ?? null;

        if ($form === null || !$this->operand instanceof StringLiteral) {
            return null;
        }
        $text = $this->operand->value();
        $kind = $form === TemporalForm::Timestamp ? 'DATETIME' : $form->value;

        return \SqlSemantics\Platform\MySql\Rules\Literal\Temporal::literal($kind, $text, 6, $settings->noZeroDate ?? false, $settings->noZeroInDate ?? false, $settings->allowInvalidDates ?? true) === null ? null : new TemporalLiteral($form, $text);
    }

    /**
     * Derives the operand and, for a temporal escape over a string, the type of the temporal literal.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        $literal = $this->literal(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context));
        if ($literal !== null) {
            return new ScalarFact(new Known(Literals::of($derivation->context)->temporal($literal)), Nullability::NotNull);
        }

        return new ScalarFact($fact->type, $fact->nullability, $fact->resolution, $fact->replacement ?? $this->operand);
    }

    /**
     * Writes the braces around the kind and the operand.
     */
    public function render(Output $out): void
    {
        $out->symbol('{')->name($this->kind, NameUse::Label)->node($this->operand)->symbol('}');
    }
}
