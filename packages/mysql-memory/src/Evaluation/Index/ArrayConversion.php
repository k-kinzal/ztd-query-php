<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Index;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Computes the converted entries of a multi-valued index for one stored row.
 *
 * SQL NULL contributes no unique-key conflict; an empty array contributes no
 * entries. JSON null and nested containers are refused. Each scalar element
 * is converted using the element domain resolved by SQL Semantics.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-multi-valued.
 *
 * @visibility MySqlMemory
 */
final class ArrayConversion implements Evaluable
{
    /**
     * @param Evaluable $document The resolved JSON document expression
     * @param Domain $elementDomain The resolved domain of each key entry
     * @param string $name The owning index name for diagnostics
     */
    public function __construct(public readonly Evaluable $document, public readonly Domain $elementDomain, public readonly string $name)
    {
    }

    /**
     * Answers the domain of the internal document containing the converted entries.
     */
    public function domain(): Domain
    {
        return new Domain(Kind::Json, Field::Json, 4294967295);
    }

    /**
     * Evaluates a stored entry at its write ordinal without changing the caller's row position.
     */
    public function at(Frame $frame, int $row): ?string
    {
        $previous = $frame->context->row;
        $frame->context->row = $row;
        try {
            return $this->evaluate($frame);
        } finally {
            $frame->context->row = $previous;
        }
    }

    /**
     * Converts the document's elements, keeping their SQL types in its internal JSON representation.
     */
    public function evaluate(Frame $frame): ?string
    {
        $value = $this->document->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $document = JsonNode::load(Jsons::cast($value, $this->document));
        if ($document->type === JsonKind::Boolean || $document->type === JsonKind::Object) {
            throw StatementError::NotSupportedYet->error('CAST-ing JSON ' . strtoupper($document->type->name) . ' type to array');
        }
        $elements = $document->type === JsonKind::Array ? $document->children() : [$document];
        $converted = [];
        $conversion = new Elements($this->elementDomain, $this->name);
        foreach ($elements as $element) {
            if ($element->type === JsonKind::Null) {
                throw ConstraintError::FunctionalJsonCast->error($this->name);
            }
            if ($element->type === JsonKind::Array || $element->type === JsonKind::Object) {
                throw ConstraintError::FunctionalNested->error($this->name);
            }
            $converted[] = $conversion->convert($element, $frame->context);
        }

        usort($converted, static fn (JsonNode $left, JsonNode $right): int => $left->compare($right));
        $unique = [];
        foreach ($converted as $entry) {
            $unique[$entry->key()] = $entry;
        }

        return (new JsonNode(JsonKind::Array, array_values($unique)))->store();
    }
}
