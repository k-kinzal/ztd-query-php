<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Model\Project;
use Requirements\Source\Registry;
use Requirements\Source\Unit;
use RuntimeException;
use Throwable;

final class Analyzer
{
    public function analyze(Project $project, bool $live = false): Analysis
    {
        $registry = new Registry($project->sourceExtensions);
        $units = [];
        $scopes = [];
        $errors = [];
        foreach ($project->sources as $source) {
            $scopes[$source->id] = [];
            try {
                $selected = $registry->get($source->format)->select($source, $source->selector, $project->directory, $live);
                if ($selected === []) {
                    throw new RuntimeException('Scope selected no source units.');
                }
                foreach ($selected as $unit) {
                    $key = $unit->key($source);
                    if ($unit->location === '' || $unit->text === '') {
                        throw new RuntimeException('Source units must have nonempty locations and text.');
                    }
                    if (isset($units[$key]) && $units[$key]->unit->text !== $unit->text) {
                        throw new RuntimeException('Conflicting snapshots for the same source unit.');
                    }
                    foreach ($units as $existing) {
                        if ($existing->source->uri === $source->uri && $existing->source->format === $source->format
                            && (str_starts_with($unit->location, $existing->unit->location . '/') || str_starts_with($existing->unit->location, $unit->location . '/'))) {
                            throw new RuntimeException('Overlapping ancestor and descendant units across source scopes.');
                        }
                    }
                    $units[$key] ??= new SourceUnit($source, $unit);
                    $scopes[$source->id][] = $key;
                }
                $scopes[$source->id] = array_values(array_unique($scopes[$source->id]));
            } catch (Throwable $error) {
                $errors[] = "$source->id: " . $error->getMessage();
            }
        }
        $evidence = [];
        $invalid = [];
        foreach ($project->items as $item) {
            $evidence[$item->id] = [];
            if ($item->source === null) {
                continue;
            }
            foreach ($item->evidence as $entry) {
                try {
                    $matches = $registry->get($item->source->format)->select($item->source, $entry->selector, $project->directory, $live);
                    if (count($matches) !== 1) {
                        throw new RuntimeException('Evidence must select exactly one unit: ' . $entry->selector);
                    }
                    $match = $matches[0];
                    $key = $match->key($item->source);
                    if (!in_array($key, $scopes[$item->source->id], true)) {
                        throw new RuntimeException('Evidence is outside the declared scope: ' . $entry->selector);
                    }
                    if (Unit::normalize($entry->quote) !== Unit::normalize($match->text)) {
                        throw new RuntimeException('Quotation differs from the complete source unit: ' . $entry->selector);
                    }
                    $evidence[$item->id][] = $key;
                } catch (Throwable $error) {
                    $invalid[$item->id] = true;
                    $errors[] = "$item->id: " . $error->getMessage();
                }
            }
        }
        foreach ($project->items as $item) {
            if ($item->kind !== 'specification') {
                continue;
            }
            $ids = [$item->id, ...$item->requirements];
            if (array_intersect($ids, array_keys($invalid)) !== []) {
                continue;
            }
            foreach ($ids as $id) {
                foreach ($evidence[$id] as $key) {
                    $units[$key]->claims[$item->id] = $item;
                }
            }
        }
        ksort($units);
        return new Analysis($units, $scopes, $errors, $evidence);
    }
}
