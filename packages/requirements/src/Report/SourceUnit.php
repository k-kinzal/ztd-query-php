<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Model\Item;
use Requirements\Model\Source;
use Requirements\Source\Unit;

final class SourceUnit
{
    /** @var array<string, Item> */
    public array $claims = [];

    public function __construct(public readonly Source $source, public readonly Unit $unit)
    {
    }

    public function supported(): bool
    {
        foreach ($this->claims as $item) {
            if ($item->status === 'supported') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, Item> $items */
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
     * @param array<string, Item> $items
     * @return array<string, mixed>
     */
    public function toArray(array $items): array
    {
        return ['uri' => $this->source->uri, 'format' => $this->source->format, 'location' => $this->unit->location, 'text' => $this->unit->text, 'claims' => array_keys($this->claims), 'status' => $this->claims === [] ? 'uncovered' : ($this->supported() ? 'supported' : 'unsupported'), 'fingerprint' => $this->fingerprint($items)];
    }
}
