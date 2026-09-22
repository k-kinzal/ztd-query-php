<?php

declare(strict_types=1);

namespace Requirements\Config;

use InvalidArgumentException;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Model\Source;
use Requirements\Test\RunnerConfig;

final class Loader
{
    public function load(string $file): Project
    {
        $path = realpath($file);
        if ($path === false) {
            throw new InvalidArgumentException("Configuration does not exist: $file");
        }
        $directory = dirname($path);
        $data = $this->document($path, 'config');
        Fields::keys($data, ['$schema', 'version', 'definitions', 'bootstrap', 'extensions', 'runners', 'coverage', 'markdown'], 'configuration');
        if (isset($data['bootstrap'])) {
            $bootstrap = $directory . '/' . Fields::text($data, 'bootstrap');
            if (!is_file($bootstrap)) {
                throw new InvalidArgumentException("Bootstrap does not exist: $bootstrap");
            }
            require_once $bootstrap;
        }
        $markdown = Fields::mapping($data['markdown'] ?? [], 'markdown');
        [$items, $sources, $files] = $this->definitions($directory, Fields::strings($data['definitions'] ?? [], 'definitions'), $markdown);
        $runners = [];
        foreach (Fields::mapping($data['runners'] ?? [], 'runners') as $name => $runner) {
            $runners[$name] = RunnerConfig::from($runner, $directory);
        }
        $this->validateLinks($items, $runners);
        $extensions = Fields::mapping($data['extensions'] ?? [], 'extensions');
        Fields::keys($extensions, ['sources', 'runners'], 'extensions');
        $coverage = Fields::mapping($data['coverage'] ?? [], 'coverage');
        Fields::keys($coverage, ['minimum', 'diff_minimum', 'sources'], 'coverage');
        $thresholds = [];
        foreach (Fields::mapping($coverage['sources'] ?? [], 'coverage.sources') as $id => $threshold) {
            if (!isset($sources[$id])) {
                throw new InvalidArgumentException("Unknown source threshold: $id");
            }
            $thresholds[$id] = Fields::percentage($threshold, "coverage.sources.$id");
        }
        $sourceClasses = $this->classes($extensions['sources'] ?? []);
        $runnerClasses = $this->classes($extensions['runners'] ?? []);
        $sourceRegistry = new \Requirements\Source\Registry($sourceClasses);
        $runnerRegistry = new \Requirements\Test\Registry($runnerClasses);
        foreach ($sources as $source) {
            $sourceRegistry->get($source->format);
        }
        foreach ($runners as $runner) {
            $runnerRegistry->get($runner->extension);
        }
        return new Project($directory, $items, $sources, $runners, $sourceClasses, $runnerClasses, Fields::percentage($coverage['minimum'] ?? 0, 'coverage.minimum'), Fields::percentage($coverage['diff_minimum'] ?? 0, 'coverage.diff_minimum'), $thresholds, [$path, ...$files], $markdown);
    }

    /**
     * @param array<string, mixed> $markdown
     * @return array<string, mixed>
     */
    public function document(string $path, string $kind = 'definition', array $markdown = []): array
    {
        $object = (new DocumentReader())->read($path, $kind, $markdown);
        return Fields::mapping(json_decode(json_encode($object, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), $path);
    }

    /**
     * @param list<string> $patterns
     * @param array<string, mixed> $markdown
     * @return array{array<string, Item>, array<string, Source>, list<string>}
     */
    private function definitions(string $directory, array $patterns, array $markdown): array
    {
        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($directory . '/' . $pattern);
            if ($matches === false || $matches === []) {
                throw new InvalidArgumentException("Definition pattern has no matches: $pattern");
            }
            array_push($files, ...$matches);
        }
        $files = array_values(array_unique($files));
        sort($files);
        if ($files === []) {
            throw new InvalidArgumentException('At least one definition is required.');
        }
        $items = [];
        $sources = [];
        foreach ($files as $file) {
            $data = $this->document($file, 'definition', $markdown);
            Fields::keys($data, ['$schema', 'version', 'source', 'items'], $file);
            if (!array_key_exists('source', $data)) {
                throw new InvalidArgumentException("$file: declare source or source: null explicitly.");
            }
            $source = $data['source'] === null ? null : Source::from($data['source']);
            if ($source !== null) {
                if (isset($sources[$source->id])) {
                    throw new InvalidArgumentException("Duplicate source ID: $source->id");
                }
                $sources[$source->id] = $source;
            }
            foreach (Fields::sequence($data['items'] ?? [], "$file.items") as $entry) {
                $item = Item::from($entry, $source, $file);
                if (isset($items[$item->id])) {
                    throw new InvalidArgumentException("Duplicate item ID: $item->id");
                }
                $items[$item->id] = $item;
            }
        }
        return [$items, $sources, $files];
    }

    /**
     * @param array<string, Item> $items
     * @param array<string, RunnerConfig> $runners
     */
    private function validateLinks(array $items, array $runners): void
    {
        foreach ($items as $item) {
            foreach ([...$item->requirements, ...$item->related] as $id) {
                if (!isset($items[$id]) || $id === $item->id) {
                    throw new InvalidArgumentException("$item->id: missing or self reference '$id'.");
                }
            }
            foreach ($item->requirements as $id) {
                if ($items[$id]->kind !== 'requirement' || $items[$id]->origin !== 'sourced') {
                    throw new InvalidArgumentException("$item->id: '$id' must be a sourced requirement.");
                }
            }
            foreach ($item->tests as $test) {
                if (!isset($runners[$test->runner])) {
                    throw new InvalidArgumentException("$item->id: unknown runner '$test->runner'.");
                }
            }
        }
    }

    /** @return array<string, string> */
    private function classes(mixed $value): array
    {
        $result = [];
        $data = Fields::mapping($value, 'extensions');
        foreach (array_keys($data) as $name) {
            $result[$name] = Fields::text($data, $name);
        }
        return $result;
    }
}
