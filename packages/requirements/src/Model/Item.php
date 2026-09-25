<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

final class Item
{
    /**
     * @param list<Excerpt> $evidence
     * @param list<TestReference> $tests
     * @param list<string> $requirements
     * @param list<string> $related
     * @param list<string> $labels
     * @param array<string, mixed> $data
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
        $item->validate();
        return $item;
    }

    private function validate(): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $this->id) !== 1) {
            throw new InvalidInputException('Item IDs must start with a letter and contain letters, digits, dots, underscores or dashes.');
        }
        if (!in_array($this->kind, ['requirement', 'specification'], true) || !in_array($this->status, ['supported', 'unsupported'], true)) {
            throw new InvalidInputException("$this->id: invalid kind or status.");
        }
        if (!in_array($this->origin, ['sourced', 'original', 'undocumented'], true)) {
            throw new InvalidInputException("$this->id: invalid origin.");
        }
        if ($this->status === 'unsupported' && $this->reason === '') {
            throw new InvalidInputException("$this->id: unsupported items require a reason.");
        }
        if ($this->kind === 'specification') {
            try {
                (new \Requirements\Ears\Validator())->validate($this->statement);
            } catch (InvalidInputException $error) {
                throw new InvalidInputException("$this->id: " . $error->getMessage(), 0, $error);
            }
        }
        if ($this->kind === 'requirement' && ($this->tests !== [] || $this->requirements !== [] || $this->status !== 'supported')) {
            throw new InvalidInputException("$this->id: requirements cannot have tests, requirement parents or unsupported status; record disposition on specifications.");
        }
        if ($this->source === null && $this->evidence !== []) {
            throw new InvalidInputException("$this->id: evidence requires a source.");
        }
        if ($this->origin !== 'sourced' && ($this->source !== null || $this->requirements !== [] || $this->reason === '')) {
            throw new InvalidInputException("$this->id: independent items require a reason and cannot claim a source or requirement.");
        }
        if ($this->origin === 'sourced' && $this->evidence === [] && $this->requirements === []) {
            throw new InvalidInputException("$this->id: provide evidence or requirements, or explicitly mark an independent origin with a reason.");
        }
        Fields::mapping($this->data['metadata'] ?? [], 'metadata');
        foreach (Fields::sequence($this->data['design'] ?? [], 'design') as $entry) {
            $design = Fields::mapping($entry, 'design');
            Fields::keys($design, ['url', 'text'], 'design');
            if ($design === []) {
                throw new InvalidInputException("$this->id: a design reference needs url or text.");
            }
            foreach (array_keys($design) as $key) {
                Fields::text($design, $key);
            }
        }
    }
}
