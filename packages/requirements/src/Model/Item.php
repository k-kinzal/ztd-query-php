<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * A requirement or specification with its source evidence, links and disposition.
 *
 * Requirements are optional upstream records quoted from a source; specifications are EARS
 * statements that claim evidence directly or through requirements, link tests, or declare
 * an independent origin with a reason.
 */
final class Item
{
    /**
     * @param string $id The item ID
     * @param string $kind "requirement" or "specification"
     * @param string $statement The requirement text or EARS statement
     * @param string $status "supported" or "unsupported"
     * @param Source|null $source The source of the item's definition file
     * @param list<Excerpt> $evidence The quoted source units
     * @param list<TestReference> $tests The linked tests
     * @param list<string> $requirements The IDs of the requirements it refines
     * @param list<string> $related The IDs of related items
     * @param list<string> $labels Free-form labels
     * @param string $category The category, or an empty string
     * @param string $origin "sourced", "original" or "undocumented"
     * @param string $reason Why the item is unsupported or independent
     * @param string $file The definition file declaring the item
     * @param array<string, mixed> $data The item as written, including design and metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $statement,
        public readonly string $status,
        public readonly ?Source $source,
        public readonly array $evidence,
        public readonly array $tests,
        public readonly array $requirements,
        public readonly array $related,
        public readonly array $labels,
        public readonly string $category,
        public readonly string $origin,
        public readonly string $reason,
        public readonly string $file,
        public readonly array $data,
    ) {
    }

    /**
     * Reads and validates an item of a definition.
     *
     * @param mixed $value The decoded item
     * @param Source|null $source The source of the definition file
     * @param string $file The definition file
     *
     * @return self The item
     *
     * @throws InvalidInputException When a field is invalid or the item breaks a disposition rule
     */
    public static function from(mixed $value, ?Source $source, string $file): self
    {
        $data = Fields::mapping($value, 'item');
        Fields::keys($data, ['id', 'kind', 'statement', 'status', 'evidence', 'tests', 'requirements', 'related', 'labels', 'category', 'origin', 'reason', 'design', 'metadata'], 'item');
        $item = new self(
            Fields::text($data, 'id'),
            Fields::text($data, 'kind', 'specification'),
            Fields::text($data, 'statement'),
            Fields::text($data, 'status', 'supported'),
            $source,
            array_map(Excerpt::from(...), Fields::sequence($data['evidence'] ?? [], 'evidence')),
            array_map(TestReference::from(...), Fields::sequence($data['tests'] ?? [], 'tests')),
            Fields::strings($data['requirements'] ?? [], 'requirements'),
            Fields::strings($data['related'] ?? [], 'related'),
            Fields::strings($data['labels'] ?? [], 'labels'),
            isset($data['category']) ? Fields::text($data, 'category') : '',
            isset($data['origin']) ? Fields::text($data, 'origin') : 'sourced',
            isset($data['reason']) ? Fields::text($data, 'reason') : '',
            $file,
            $data,
        );
        (new ItemValidator())->validate($item);
        return $item;
    }
}
