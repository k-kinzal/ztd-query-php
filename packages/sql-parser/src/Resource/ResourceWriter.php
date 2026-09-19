<?php

declare(strict_types=1);

namespace SqlParser\Resource;

use RuntimeException;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableFile;

/**
 * Writes the generated resources of one release where the registry expects them.
 *
 * @visibility root
 */
final class ResourceWriter
{
    /**
     * @param TableFile $tables Stores parse tables
     */
    public function __construct(private readonly TableFile $tables = new TableFile())
    {
    }

    /**
     * Writes the parse table of a release.
     *
     * @param SqlVersion $version The release
     * @param ParseTable $table Its table
     *
     * @throws RuntimeException When the file cannot be written
     */
    public function writeTable(SqlVersion $version, ParseTable $table): void
    {
        $this->ensureDirectory($version->tablePath);
        $this->tables->save($table, $version->tablePath);
    }

    /**
     * Writes the keyword table of a release as a PHP file returning an array.
     *
     * @param SqlVersion $version The release
     * @param array<string, array<string, string>> $keywords Terminal name by spelling, by group
     * @param string $origin Where the keywords were read from
     *
     * @throws RuntimeException When the file cannot be written
     */
    public function writeKeywords(SqlVersion $version, array $keywords, string $origin): void
    {
        $this->ensureDirectory($version->keywordPath);
        $body = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Keyword tokens of {$version->name}, generated from {$origin}.\n *\n * @return array<string, array<string, string>>\n */\nreturn " . $this->export($keywords, 0) . ";\n";
        if (file_put_contents($version->keywordPath, $body) === false) {
            throw new RuntimeException("Cannot write keyword table to {$version->keywordPath}");
        }
    }

    /**
     * Renders a nested array of strings as PHP source, one entry per line.
     *
     * @param array<string, array<string, string>|string> $values Values to render
     * @param int $indent Nesting depth
     *
     * @return string PHP source of the array
     */
    public function export(array $values, int $indent): string
    {
        $pad = str_repeat('    ', $indent + 1);
        $lines = ['['];
        foreach ($values as $key => $value) {
            $rendered = is_array($value) ? $this->export($value, $indent + 1) : var_export($value, true);
            $lines[] = $pad . var_export($key, true) . ' => ' . $rendered . ',';
        }
        $lines[] = str_repeat('    ', $indent) . ']';

        return implode("\n", $lines);
    }

    /**
     * Makes sure the directory of a path exists.
     *
     * @param string $path File about to be written
     *
     * @throws RuntimeException When the directory cannot be made
     */
    public function ensureDirectory(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
    }
}
