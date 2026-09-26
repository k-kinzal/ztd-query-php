<?php

declare(strict_types=1);

namespace Requirements\Console;

use JsonException;
use Requirements\Config\DocumentReader;
use Requirements\Input\InvalidInputException;
use RuntimeException;

/**
 * Rewrites configuration and definition documents in their canonical form.
 */
final class Formatter
{
    /**
     * Formats documents, or only reports which would change.
     *
     * @param list<string> $files The configuration file followed by the definition files
     * @param bool $check Whether to leave the files unchanged
     * @param array<string, mixed> $markdown The markdown options
     *
     * @return list<string> The files whose text differs from the canonical form
     *
     * @throws InvalidInputException When a document is invalid
     * @throws JsonException When a document cannot be converted
     * @throws RuntimeException When a file cannot be written
     */
    public function format(array $files, bool $check, array $markdown = []): array
    {
        $changed = [];
        foreach ($files as $index => $file) {
            $reader = new DocumentReader();
            $data = $reader->read($file, $index === 0 ? 'config' : 'definition', $markdown, dirname($files[0]));
            $text = DocumentReader::isMarkdown($file) ? $reader->markdown->render($data) : DocumentReader::yaml($data);
            if ($text !== file_get_contents($file)) {
                $changed[] = $file;
                if (!$check && file_put_contents($file, $text) === false) {
                    throw new RuntimeException("Cannot format $file");
                }
            }
        }
        return $changed;
    }
}
