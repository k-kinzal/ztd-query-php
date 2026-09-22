<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Config\DocumentReader;
use Requirements\Config\MarkdownDocument;
use RuntimeException;

final class Formatter
{
    /**
     * @param list<string> $files
     * @param array<string, mixed> $markdown
     * @return list<string>
     */
    public function format(array $files, bool $check, array $markdown = []): array
    {
        $changed = [];
        foreach ($files as $index => $file) {
            $data = (new DocumentReader())->read($file, $index === 0 ? 'config' : 'definition', $markdown);
            $text = DocumentReader::isMarkdown($file) ? (new MarkdownDocument())->render($data) : DocumentReader::yaml($data);
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
