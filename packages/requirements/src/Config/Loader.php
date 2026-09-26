<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Project;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Test\Registry as RunnerRegistry;
use Requirements\Test\RunnerConfig;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Loads a project from its requirements.yaml and the definition files it names.
 *
 * Every document is validated against its schema, every item against the definition rules,
 * links between items and to runners are resolved, and each source format and runner
 * extension must be registered.
 */
final class Loader
{
    /**
     * Loads and validates a project.
     *
     * @param string $file The configuration file
     *
     * @return Project The project
     *
     * @throws InvalidInputException When the configuration, a definition or a link is invalid
     * @throws JsonException When a document cannot be converted
     * @throws ParseException When a YAML document is malformed
     */
    public function load(string $file): Project
    {
        $path = realpath($file);
        if ($path === false) {
            throw new InvalidInputException("Configuration does not exist: $file");
        }
        $directory = dirname($path);
        $data = $this->document($path, 'config');
        Fields::keys($data, ['$schema', 'version', 'definitions', 'bootstrap', 'extensions', 'runners', 'coverage', 'markdown'], 'configuration');
        if (isset($data['bootstrap'])) {
            (new Bootstrap())->load($directory . '/' . Fields::text($data, 'bootstrap'));
        }
        $markdown = Fields::mapping($data['markdown'] ?? [], 'markdown');
        $definitions = (new DefinitionReader())->read($directory, Fields::strings($data['definitions'] ?? [], 'definitions'), $markdown);
        $runners = [];
        foreach (Fields::mapping($data['runners'] ?? [], 'runners') as $name => $runner) {
            $runners[$name] = RunnerConfig::from($runner, $directory);
        }
        (new LinkValidator())->validate($definitions->items, $runners);
        $extensions = Fields::mapping($data['extensions'] ?? [], 'extensions');
        Fields::keys($extensions, ['sources', 'runners'], 'extensions');
        $coverage = Fields::mapping($data['coverage'] ?? [], 'coverage');
        Fields::keys($coverage, ['minimum', 'diff_minimum', 'sources'], 'coverage');
        $thresholds = (new CoverageThresholds())->read($coverage['sources'] ?? [], $definitions->sources);
        $sourceClasses = ExtensionClasses::read($extensions['sources'] ?? []);
        $runnerClasses = ExtensionClasses::read($extensions['runners'] ?? []);
        $sourceRegistry = new SourceRegistry($sourceClasses);
        $runnerRegistry = new RunnerRegistry($runnerClasses);
        foreach ($definitions->sources as $source) {
            $sourceRegistry->get($source->format);
        }
        foreach ($runners as $runner) {
            $runnerRegistry->get($runner->extension);
        }
        return new Project($directory, $definitions->items, $definitions->sources, $runners, $sourceClasses, $runnerClasses, Fields::percentage($coverage['minimum'] ?? 0, 'coverage.minimum'), Fields::percentage($coverage['diff_minimum'] ?? 0, 'coverage.diff_minimum'), $thresholds, [$path, ...$definitions->files], $markdown);
    }

    /**
     * Reads and validates one document as a mapping.
     *
     * @param string $path The document file
     * @param string $kind "config" or "definition", naming the schema
     * @param array<string, mixed> $markdown The markdown options
     *
     * @return array<string, mixed> The document
     *
     * @throws InvalidInputException When the document breaks its schema or is not a mapping
     * @throws JsonException When the document cannot be converted
     * @throws ParseException When a YAML document is malformed
     */
    public function document(string $path, string $kind = 'definition', array $markdown = []): array
    {
        return DocumentReader::mapping((new DocumentReader())->read($path, $kind, $markdown), $path);
    }
}
