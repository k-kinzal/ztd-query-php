<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Ears\Validator;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Enforces the rules an item must satisfy on its own, before links between items are checked.
 */
final class ItemValidator
{
    /**
     * Checks every rule, in the order the messages are reported.
     *
     * @param Item $item The item
     *
     * @throws InvalidInputException When the item breaks a rule; the message starts with its ID
     */
    public function validate(Item $item): void
    {
        $this->identity($item);
        $this->statement($item);
        $this->disposition($item);
        $this->details($item);
    }

    /**
     * Checks the ID and the kind, status and origin values.
     *
     * @param Item $item The item
     *
     * @throws InvalidInputException When the ID is malformed or a value is unknown
     */
    public function identity(Item $item): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $item->id) !== 1) {
            throw new InvalidInputException('Item IDs must start with a letter and contain letters, digits, dots, underscores or dashes.');
        }
        if (!in_array($item->kind, ['requirement', 'specification'], true) || !in_array($item->status, ['supported', 'unsupported'], true)) {
            throw new InvalidInputException("$item->id: invalid kind or status.");
        }
        if (!in_array($item->origin, ['sourced', 'original', 'undocumented'], true)) {
            throw new InvalidInputException("$item->id: invalid origin.");
        }
    }

    /**
     * Checks that unsupported items give a reason and specifications follow EARS.
     *
     * @param Item $item The item
     *
     * @throws InvalidInputException When the reason is missing or the statement breaks EARS
     */
    public function statement(Item $item): void
    {
        if ($item->status === 'unsupported' && $item->reason === '') {
            throw new InvalidInputException("$item->id: unsupported items require a reason.");
        }
        if ($item->kind === 'specification') {
            try {
                (new Validator())->validate($item->statement);
            } catch (InvalidInputException $error) {
                throw new InvalidInputException("$item->id: " . $error->getMessage(), 0, $error);
            }
        }
    }

    /**
     * Checks how the item is accounted for: by evidence, by requirements or as independent.
     *
     * @param Item $item The item
     *
     * @throws InvalidInputException When the kind, origin, evidence and links contradict each other
     */
    public function disposition(Item $item): void
    {
        if ($item->kind === 'requirement' && ($item->tests !== [] || $item->requirements !== [] || $item->status !== 'supported')) {
            throw new InvalidInputException("$item->id: requirements cannot have tests, requirement parents or unsupported status; record disposition on specifications.");
        }
        if ($item->source === null && $item->evidence !== []) {
            throw new InvalidInputException("$item->id: evidence requires a source.");
        }
        if ($item->origin !== 'sourced' && ($item->source !== null || $item->requirements !== [] || $item->reason === '')) {
            throw new InvalidInputException("$item->id: independent items require a reason and cannot claim a source or requirement.");
        }
        if ($item->origin === 'sourced' && $item->evidence === [] && $item->requirements === []) {
            throw new InvalidInputException("$item->id: provide evidence or requirements, or explicitly mark an independent origin with a reason.");
        }
    }

    /**
     * Checks the metadata mapping and the design references.
     *
     * @param Item $item The item
     *
     * @throws InvalidInputException When metadata is not a mapping or a design reference is empty or unknown
     */
    public function details(Item $item): void
    {
        Fields::mapping($item->data['metadata'] ?? [], 'metadata');
        foreach (Fields::sequence($item->data['design'] ?? [], 'design') as $entry) {
            $design = Fields::mapping($entry, 'design');
            Fields::keys($design, ['url', 'text'], 'design');
            if ($design === []) {
                throw new InvalidInputException("$item->id: a design reference needs url or text.");
            }
            foreach (array_keys($design) as $key) {
                Fields::text($design, $key);
            }
        }
    }
}
