<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Reads the definition files that the configuration's patterns match.
 *
 * Files are read in path order; item and source IDs must be unique across them, and Markdown
 * links to other items must point to the files that define them.
 */
final class DefinitionReader
{
    /**
     * Reads every matched definition file.
     *
     * @param string $directory The configuration directory the patterns resolve against
     * @param list<string> $patterns Glob patterns of definition files
     * @param array<string, mixed> $markdown The markdown options
     *
     * @return Definitions The items, sources and files
     *
     * @throws InvalidInputException When a pattern matches nothing, a definition is invalid or an ID repeats
     * @throws JsonException When a document cannot be converted
     * @throws ParseException When a YAML definition is malformed
     */
    public function read(string $directory, array $patterns, array $markdown): Definitions
    {
        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($directory . '/' . $pattern);
            if ($matches === false || $matches === []) {
                throw new InvalidInputException("Definition pattern has no matches: $pattern");
            }
            array_push($files, ...$matches);
        }
        $files = array_values(array_unique($files));
        sort($files);
        if ($files === []) {
            throw new InvalidInputException('At least one definition is required.');
        }
        $items = [];
        $sources = [];
        $references = [];
        foreach ($files as $file) {
            $reader = new DocumentReader();
            $data = DocumentReader::mapping($reader->read($file, 'definition', $markdown, $directory), $file);
            array_push($references, ...$reader->markdown->references());
            Fields::keys($data, ['$schema', 'version', 'source', 'items'], $file);
            if (!array_key_exists('source', $data)) {
                throw new InvalidInputException("$file: declare source or source: null explicitly.");
            }
            $source = $data['source'] === null ? null : Source::from($data['source']);
            if ($source !== null) {
                $sources[$source->id] = isset($sources[$source->id]) ? throw new InvalidInputException("Duplicate source ID: $source->id") : $source;
            }
            foreach (Fields::sequence($data['items'] ?? [], "$file.items") as $entry) {
                $item = Item::from($entry, $source, $file);
                $items[$item->id] = isset($items[$item->id]) ? throw new InvalidInputException("Duplicate item ID: $item->id") : $item;
            }
        }
        foreach ($references as $reference) {
            $reference->validate($items);
        }
        return new Definitions($items, $sources, $files);
    }
}
