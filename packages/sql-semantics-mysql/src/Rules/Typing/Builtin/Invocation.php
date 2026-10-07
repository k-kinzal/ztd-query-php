<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Scalar;

/**
 * One call of a built-in function whose arguments resolved: their types and nodes, and the session.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class Invocation
{
    /**
     * @param list<Domain> $domains The types of the arguments
     * @param list<Scalar> $nodes The arguments
     * @param Settings $settings The session the call is resolved in
     * @param Derivation $derivation The derivation conflicts are reported to
     */
    public function __construct(public readonly array $domains, public readonly array $nodes, public readonly Settings $settings, public readonly Derivation $derivation)
    {
    }

    /**
     * Answers the type of an argument.
     */
    public function domain(int $index): Domain
    {
        return $this->domains[$index] ?? Domain::null();
    }

    /**
     * Answers the integer an argument writes as a literal, or null when it writes none.
     *
     * A string literal counts by the digits it starts with, as the server reads it as a number.
     */
    public function constant(int $index): ?int
    {
        $node = $this->nodes[$index] ?? null;
        $negative = false;
        while ($node instanceof Grouped || ($node instanceof Unary && $node->operator === UnaryOperator::Minus)) {
            $negative = $negative !== ($node instanceof Unary);
            $node = $node->operand;
        }
        $text = match (true) {
            $node instanceof NumberLiteral && $node->form === NumberForm::Integer => $node->text,
            $node instanceof StringLiteral && preg_match('/\A\s*[0-9]+/', $node->value(), $digits) === 1 => trim($digits[0]),
            default => null,
        };

        return $text === null ? null : ($negative ? -1 : 1) * (int) min((float) $text, (float) PHP_INT_MAX);
    }

    /**
     * Answers the length in characters of a value written as text.
     */
    public function length(Domain $domain): int
    {
        return match ($domain->kind) {
            Kind::Double => $domain->decimals < Domain::NOT_FIXED ? $domain->length : 22,
            Kind::Null => 0,
            Kind::Integer, Kind::Decimal, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit => $domain->length,
        };
    }

    /**
     * Resolves a string result of a length in the collation some arguments aggregate to, or null after a conflict.
     *
     * @param list<Domain> $domains The arguments whose collations take part
     */
    public function text(array $domains, int $length, string $operation): ?Domain
    {
        $settled = $this->collations()->aggregate($domains, $operation, $this->derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;

        return Domain::string(min($length, 4294967295), $collation, $length > 16383 ? Field::Blob : Field::VarString, $coercibility);
    }

    /**
     * Answers how the collations of string arguments settle.
     */
    public function collations(): Collations
    {
        return new Collations($this->settings->connection);
    }

    /**
     * Answers how the types of branches settle.
     */
    public function aggregation(): Aggregation
    {
        return new Aggregation($this->collations());
    }
}
