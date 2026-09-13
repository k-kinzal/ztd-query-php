<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require getcwd() . '/packages/ztd-query-mysqli-adapter/vendor/autoload.php';

$root = getcwd();
$output = $root . '/build/docgen-inputs';
$combined = ['deptrac' => ['layers' => [], 'ruleset' => []]];

foreach (array_slice($argv, 1) as $package) {
    $directory = $root . '/' . $package;
    $name = basename($package);
    $configuration = Yaml::parseFile($directory . '/deptrac.yaml')['deptrac'];
    foreach ($configuration['layers'] as $layer) {
        $layer['name'] = $name . '/' . $layer['name'];
        foreach ($layer['collectors'] as &$collector) {
            if ($collector['type'] === 'directory') {
                $collector['value'] = preg_quote($package, '#') . '/' . ltrim($collector['value'], './');
            } elseif ($collector['type'] === 'classLike') {
                $collector['type'] = 'classNameRegex';
            }
        }
        unset($collector);
        $combined['deptrac']['layers'][] = $layer;
    }
    foreach ($configuration['ruleset'] as $layer => $allowed) {
        $combined['deptrac']['ruleset'][$name . '/' . $layer] = array_map(
            static fn (string $dependency): string => $name . '/' . $dependency,
            $allowed,
        );
    }

    $coverage = $directory . '/build/coverage-xml';
    $index = new DOMDocument();
    if (!$index->load($coverage . '/index.xml')) {
        throw new RuntimeException('Cannot load coverage index for ' . $package);
    }
    $project = $index->getElementsByTagName('project')->item(0);
    if (!$project instanceof DOMElement) {
        throw new RuntimeException('Coverage index has no project for ' . $package);
    }
    $source = $project->getAttribute('source');
    if (!str_starts_with($source, $root . '/')) {
        throw new RuntimeException('Coverage source must be inside the repository: ' . $source);
    }
    $prefix = substr($source, strlen($root) + 1);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($coverage, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'xml' || $file->getBasename() === 'index.xml') {
            continue;
        }
        $document = new DOMDocument();
        if (!$document->load($file->getPathname())) {
            throw new RuntimeException('Cannot load coverage report: ' . $file->getPathname());
        }
        foreach ($document->getElementsByTagName('file') as $coveredFile) {
            $coveredFile->setAttribute('path', $prefix . '/' . trim($coveredFile->getAttribute('path'), '/'));
        }
        $target = $output . '/coverage/' . $name . '/' . substr($file->getPathname(), strlen($coverage) + 1);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        if ($document->save($target) === false) {
            throw new RuntimeException('Cannot write combined coverage report: ' . $target);
        }
    }
}

file_put_contents($output . '/deptrac.yaml', Yaml::dump($combined, 8, 2));
