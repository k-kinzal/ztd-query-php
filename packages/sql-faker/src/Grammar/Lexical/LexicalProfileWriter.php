<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use RuntimeException;
use SqlFaker\Grammar\Source\ArtifactDirectory;
use SqlFaker\Grammar\Source\SqlVersion;

/**
 * Writes a generated profile and its grammar to the files they are read from.
 *
 * A profile and the AST it was built against describe one release together, so
 * publishing one without the other would leave the package generating against
 * a grammar its lexer does not match. Both are staged beside their destination
 * and renamed into place, which is the only way to replace two files without a
 * window where one has moved and the other has not.
 */
final class LexicalProfileWriter
{
    /**
     * @param ArtifactDirectory $directory Answers where an artifact is staged and published
     */
    public function __construct(private readonly ArtifactDirectory $directory = new ArtifactDirectory())
    {
    }

    /**
     * Publishes the grammar and lexical profile only after both have been generated and validated.
     *
     * @param array<string, mixed> $profile
     *
     * @throws RuntimeException When either artifact cannot be written
     */
    public function publishVersion(SqlVersion $version, string $ast, array $profile): void
    {
        $artifacts = [
            $version->astPath => $ast,
            $version->lexicalPath => $this->rendered($profile),
        ];
        $temporaryPaths = [];
        try {
            foreach ($artifacts as $path => $contents) {
                $directory = $this->directory->prepared($path);
                $temporaryPath = tempnam($directory, '.sql-faker-');
                if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
                    throw new RuntimeException("Failed to stage {$path}");
                }
                $temporaryPaths[$path] = $temporaryPath;
            }
            foreach ($temporaryPaths as $path => $temporaryPath) {
                if (!rename($temporaryPath, $path)) {
                    throw new RuntimeException("Failed to publish {$path}");
                }
                unset($temporaryPaths[$path]);
                fwrite(STDOUT, "Generated: {$path}\n");
            }
        } finally {
            foreach ($temporaryPaths as $temporaryPath) {
                if (file_exists($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function rendered(array $profile): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated lexical profile.\n *\n * @return array<string, mixed>\n */\nreturn "
            . $this->exported($this->compacted($profile))
            . ";\n";
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     *
     * @throws RuntimeException When a witness is not shaped like one
     */
    public function compacted(array $profile): array
    {
        if (!isset($profile['catalog']) || !is_array($profile['catalog'])
            || !isset($profile['catalog']['terminals']) || !is_array($profile['catalog']['terminals'])
        ) {
            return $profile;
        }

        $terminals = [];
        foreach ($profile['catalog']['terminals'] as $terminal => $witnesses) {
            if (!is_array($witnesses)) {
                throw new RuntimeException('Invalid lexical terminal witnesses while compacting.');
            }
            foreach ($witnesses as $witness) {
                if (!is_array($witness)
                    || !isset($witness['id'], $witness['sql'], $witness['tokens'], $witness['units'])
                ) {
                    throw new RuntimeException('Invalid lexical witness while compacting.');
                }
                $compact = [$witness['id'], $witness['sql'], $witness['tokens'], $witness['units']];
                if (isset($witness['context_sql'])) {
                    $compact[] = $witness['context_sql'];
                }
                $terminals[$terminal][] = $compact;
            }
        }
        $profile['catalog']['terminals'] = $terminals;

        return $profile;
    }

    /**
     * Renders one value as the PHP literal a profile file is written from.
     *
     * A profile carries SQL that the server's lexer accepts, including control
     * characters that var_export() would write literally into the file. Those
     * are escaped by hand so the generated file stays readable and reloadable.
     *
     * @param mixed $value Value to render
     * @param int $indent Current indentation, in spaces
     *
     * @return string The value as PHP source
     *
     * @throws RuntimeException When a string cannot be escaped
     */
    public function exported(mixed $value, int $indent = 0): string
    {
        if (is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
            $escaped = preg_replace_callback(
                '/[\x00-\x1F\x7F]/',
                static fn (array $match): string => sprintf('\\x%02X', ord($match[0])),
                $escaped,
            );
            if ($escaped === null) {
                throw new RuntimeException('Failed to escape a lexical profile string.');
            }

            return '"' . $escaped . '"';
        }
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = $this->exported($item, $indent);
            }

            return '[' . implode(', ', $items) . ']';
        }

        $padding = str_repeat(' ', $indent);
        $childPadding = str_repeat(' ', $indent + 4);
        $lines = [];
        foreach ($value as $key => $item) {
            $lines[] = $childPadding . var_export($key, true) . ' => ' . $this->exported($item, $indent + 4) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n{$padding}]";
    }
}
