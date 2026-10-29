<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Index;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Converts one JSON scalar to an index entry, preserving index diagnostics.
 *
 * Conversion conditions name the index rather than its internal computed slot.
 * Failed element conversion also records the range condition of the index.
 * Verified through SQL writes on MySQL 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class Elements
{
    /**
     * @param Domain $domain The entry domain resolved by Semantics
     * @param string $name The owning index name
     */
    public function __construct(public readonly Domain $domain, public readonly string $name)
    {
    }

    /**
     * Converts an entry, retaining the outer statement's diagnostic severity.
     *
     * @throws SqlError When an element cannot be represented in the index
     */
    public function convert(JsonNode $element, Context $context): JsonNode
    {
        $local = new Context($context->modes, new Diagnostics(), $context->variables, $context->started, true);
        $local->row = $context->row;
        $column = new ColumnDefinition($this->name, $this->domain, Fill::none());
        try {
            [$value, $from] = $this->source($element);
            $converted = (new Store($local, $local->row))->value($value, $from, $column);
        } catch (SqlError $error) {
            if (!$context->strict && ($error->error === DataError::DataTooLong || $this->domain->kind->temporal())) {
                $context->diagnostics->warning(ConstraintError::FunctionalTruncated, ConstraintError::FunctionalTruncated->message($this->name, $context->row));
                throw new SqlError(ConstraintError::FunctionalRange, ConstraintError::FunctionalRange->message($this->name, $context->row), $error);
            }
            throw $this->failure($error, $context->row);
        }
        foreach ($local->diagnostics->conditions as [$level, $code]) {
            if ($level === 'Note' && $code === DataError::DataTruncated->value) {
                $context->note(ConstraintError::FunctionalTruncated, $this->name, $context->row);
            }
        }
        if ($converted === null) {
            throw ConstraintError::FunctionalJsonCast->error($this->name);
        }
        if ($this->domain->kind === Kind::String && !$this->domain->collation->bytes()) {
            return new JsonNode(JsonKind::String, (string) $converted);
        }

        return Jsons::value($converted, $this->domain, false);
    }

    /**
     * Reads an entry without turning JSON strings into quoted SQL strings.
     *
     * @return array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal, Domain}
     */
    public function source(JsonNode $element): array
    {
        if ($element->type === JsonKind::Boolean && $this->domain->kind->temporal()) {
            throw ConstraintError::FunctionalTruncated->error($this->name, 1);
        }
        if ($element->type === JsonKind::String && $this->domain->kind === Kind::Integer && preg_match('/\A\s*[+-]?[0-9]+\z/', $element->scalar()) !== 1) {
            throw ConstraintError::FunctionalJsonCast->error($this->name);
        }
        if ($this->domain->kind === Kind::String || $element->type === JsonKind::String || $element->type->quoted()) {
            $text = $element->unquoted();

            return [$text, Domain::string(strlen($text), Collation::known('utf8mb4_bin'))];
        }
        if ($element->type === JsonKind::Boolean) {
            return [$element->value === true ? 1 : 0, Domain::integer()];
        }
        if (is_float($element->value)) {
            return [$element->value, Domain::double()];
        }

        $number = $element->type === JsonKind::Unsigned && $this->domain->kind === Kind::Decimal
            ? (string) \MySqlMemory\Value\Integer::fromUnsignedText($element->scalar()) : $element->scalar();

        return [$number, Domain::decimal(65, 30)];
    }

    /**
     * Translates a conversion condition without swallowing unrelated SQL failures.
     */
    public function failure(SqlError $error, int $row): SqlError
    {
        $code = match ($error->error) {
            ConstraintError::FunctionalJsonCast => ConstraintError::FunctionalJsonCast,
            ConstraintError::FunctionalTruncated => ConstraintError::FunctionalTruncated,
            DataError::DataTooLong => ConstraintError::FunctionalTooLong,
            DataError::OutOfRange => ConstraintError::FunctionalRange,
            DataError::TruncatedWrongValueForField, DataError::DataTruncated, DataError::TruncatedWrongValue => $this->domain->kind->temporal() ? ConstraintError::FunctionalTruncated : ConstraintError::FunctionalJsonCast,
            default => throw $error,
        };

        return new SqlError($code, $code->message($this->name, $row), $error, [[ConstraintError::FunctionalRange->value, ConstraintError::FunctionalRange->message($this->name, $row)]]);
    }
}
