<?php

declare(strict_types=1);

namespace Requirements\Report;

use JsonException;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Requirements\Source\Unit;

/**
 * A unit in scope together with the specifications that claim it.
 */
final class SourceUnit
{
    /**
     * @var array<string, Item> The claiming specifications by ID
     */
    public array $claims = [];

    /**
     * @param Source $source The source whose scope selected the unit
     * @param Unit $unit The unit
     */
    public function __construct(public readonly Source $source, public readonly Unit $unit)
    {
    }

    /**
     * Tells whether a supported specification claims the unit.
     *
     * @return bool True when at least one claim is supported
     */
    public function supported(): bool
    {
        foreach ($this->claims as $item) {
            if ($item->status === 'supported') {
                return true;
            }
        }
        return false;
    }

    /**
     * Fingerprints the unit text and the records of every claim and the requirements they refine.
     *
     * @param array<string, Item> $items Every item by ID
     *
     * @return string The SHA-256 hex digest
     *
     * @throws JsonException When a record cannot be encoded
     */
    public function fingerprint(array $items): string
    {
        $claims = [];
        foreach ($this->claims as $id => $item) {
            $claims[$id] = $item->data;
            foreach ($item->requirements as $requirement) {
                $claims[$requirement] = $items[$requirement]->data;
            }
        }
        ksort($claims);
        return hash('sha256', json_encode([$this->unit->text, $claims], JSON_THROW_ON_ERROR));
    }

    /**
     * Returns the unit as a coverage report record.
     *
     * @param array<string, Item> $items Every item by ID
     *
     * @return array<string, mixed> The location, text, claims, status and fingerprint
     *
     * @throws JsonException When a record cannot be encoded
     */
    public function toArray(array $items): array
    {
        return ['uri' => $this->source->uri, 'format' => $this->source->format, 'location' => $this->unit->location, 'text' => $this->unit->text, 'claims' => array_keys($this->claims), 'status' => $this->claims === [] ? 'uncovered' : ($this->supported() ? 'supported' : 'unsupported'), 'fingerprint' => $this->fingerprint($items)];
    }
}
