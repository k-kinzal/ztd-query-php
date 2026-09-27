<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization;

use Deriver\Exception\InvalidInputException;
use Deriver\Result\Serialization\Decode\Fields;
use Deriver\Result\Serialization\Decode\Scalar;
use Deriver\Value\Term;
use JsonException;
use stdClass;

/**
 * Reconstructs shared immutable terms from a version 1 report's value graph.
 *
 * Redacted records become confidential opaque terms; their missing payload is never
 * mistaken for a known null. This reader validates value records and references.
 * Validate the complete report against the packaged schema when consuming other fields.
 *
 * @visibility public
 * @example Reading a lossless scalar from a report
 *     $json = '{"schemaVersion":"1","values":{"v0":{"kind":"constant","literal":{"type":"int64","value":"42"},"operands":[],"attributes":[],"redacted":false,"secret":false}}}';
 *     \Deriver\Result\Serialization\ValueReader::fromJson($json)->read('v0')->native() // => 42
 */
final class ValueReader
{
    /**
     * @var array<string, Term> Interned decoded terms
     */
    private array $decoded = [];
    /**
     * @var array<string, true> Active recursion stack for cycle detection
     */
    private array $active = [];
    /**
     * @var array<string, int> Longest validated dependency path for each interned term
     */
    private array $depths = [];

    /**
     * @param stdClass $records Captured value table
     * @param int $maximumDepth Maximum nesting before rejecting the input
     */
    private function __construct(private readonly stdClass $records, private readonly int $maximumDepth)
    {
    }

    /**
     * Captures and validates every value record, including unreferenced records.
     * @param string $json UTF-8 JSON report bytes
     * @param int $maximumNodes Maximum serialized value records
     * @param int $maximumDepth Maximum value graph depth
     * @return self Validated shared value graph
     * @throws JsonException If the document is not valid JSON
     * @throws InvalidInputException If the version, limits, records, or references are invalid
     */
    public static function fromJson(string $json, int $maximumNodes = 20000, int $maximumDepth = 128): self
    {
        if ($maximumNodes < 1 || $maximumDepth < 1 || $maximumDepth > 512 || strlen($json) > 33554432) {
            throw new InvalidInputException('Invalid value-reader limits or report larger than 32 MiB.');
        }
        $document = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (!$document instanceof stdClass || (new Fields())->text($document, 'schemaVersion') !== '1') {
            throw new InvalidInputException('Unsupported Deriver report schema version.');
        }
        $records = (new Fields())->object($document, 'values');
        if (count(get_object_vars($records)) > $maximumNodes) {
            throw new InvalidInputException('The serialized value graph exceeds its node limit.');
        }
        $reader = new self($records, $maximumDepth);
        foreach (get_object_vars($records) as $id => $record) {
            if (!is_string($id) || preg_match('/\Av[0-9]+\z/', $id) !== 1 || !$record instanceof stdClass) {
                throw new InvalidInputException('Invalid value table identifier or record.');
            }
            $reader->read($id);
        }
        return $reader;
    }

    /**
     * Returns one interned term and resolves its ordered dependencies.
     * @param string $id Serialized value identifier
     * @return Term Shared immutable term
     * @throws InvalidInputException If the reference is missing, cyclic, or too deep
     */
    public function read(string $id): Term
    {
        if (isset($this->decoded[$id])) {
            return $this->decoded[$id];
        }
        if (isset($this->active[$id]) || count($this->active) >= $this->maximumDepth) {
            throw new InvalidInputException('Cyclic or excessively deep value graph.');
        }
        $record = (new Fields())->object($this->records, $id);
        $this->active[$id] = true;
        $kind = (new JsonText())->decode((new Fields())->text($record, 'kind'));
        if ($kind === '') {
            throw new InvalidInputException('Value kinds must not be empty.');
        }
        $redacted = (new Fields())->boolean($record, 'redacted');
        $secret = (new Fields())->boolean($record, 'secret');
        $operands = $this->operands($record);
        $attributes = $this->attributes($record);
        $literal = (new Scalar())->read((new Fields())->object($record, 'literal'));
        if ($redacted && ($literal !== null || $operands !== [] || $attributes !== [])) {
            throw new InvalidInputException('A redacted record must not contain a payload.');
        }
        $term = $redacted ? new Term('opaque', 'REDACTED', attributes: ['redactedKind' => $kind, 'type' => 'mixed'], secret: true) : new Term($kind, $literal, $operands, $attributes, $secret);
        $depth = 1;
        foreach ((new Fields())->objects($record, 'operands') as $operand) {
            $depth = max($depth, 1 + $this->depths[(new Fields())->text($operand, 'value')]);
        }
        if ($depth > $this->maximumDepth) {
            throw new InvalidInputException('The value graph exceeds its depth limit.');
        }
        $this->depths[$id] = $depth;
        unset($this->active[$id]);
        return $this->decoded[$id] = $term;
    }

    /**
     * Decodes ordered PHP keys and validates every dependency reference.
     * @param stdClass $record Serialized node
     * @return array<int|string, Term> Ordered operands
     * @throws InvalidInputException If keys or references are invalid
     */
    public function operands(stdClass $record): array
    {
        $operands = [];
        foreach ((new Fields())->objects($record, 'operands') as $operand) {
            $key = (new Scalar())->read((new Fields())->object($operand, 'key'));
            if ((!is_int($key) && !is_string($key)) || array_key_exists($key, $operands) || array_key_first([$key => true]) !== $key) {
                throw new InvalidInputException('Invalid, duplicate, or noncanonical PHP operand key.');
            }
            $operands[$key] = $this->read((new Fields())->text($operand, 'value'));
        }
        return $operands;
    }

    /**
     * Decodes scalar metadata without adopting arbitrary serialized PHP objects.
     * @param stdClass $record Serialized node
     * @return array<string, int|float|string|bool|null> Semantic attributes
     * @throws InvalidInputException If attribute records are malformed or duplicated
     */
    public function attributes(stdClass $record): array
    {
        $attributes = [];
        foreach ((new Fields())->objects($record, 'attributes') as $attribute) {
            $key = (new JsonText())->decode((new Fields())->text($attribute, 'key'));
            if (array_key_exists($key, $attributes)) {
                throw new InvalidInputException('Duplicate value attribute.');
            }
            $attributes[$key] = (new Scalar())->read((new Fields())->object($attribute, 'value'));
        }
        return $attributes;
    }
}
