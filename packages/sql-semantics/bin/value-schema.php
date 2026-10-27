<?php

declare(strict_types=1);

// Review the resulting diff before accepting a change to retained semantic data.
require getcwd() . '/vendor/autoload.php';

$directory = getcwd();
$composer = json_decode(file_get_contents($directory . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$schema = [];
$audit = new SqlSemantics\Validation\ValueSchema();
foreach ($composer['autoload']['psr-4'] as $namespace => $relative) {
    $root = realpath($directory . '/' . $relative);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $class = $namespace . str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -4));
        if (!class_exists($class) && !enum_exists($class)) {
            continue;
        }
        $reflection = new ReflectionClass($class);
        if ($reflection->isEnum() || in_array(SqlSemantics\Statement\Snapshot::class, $reflection->getTraitNames(), true) || in_array(SqlSemantics\Statement\Semantics\Occurrence::class, $reflection->getTraitNames(), true)) {
            $schema[$class] = $audit->fields($class);
        }
    }
}
ksort($schema);
$path = $directory . '/resources/value-schema.json';
if (in_array('--write', $argv, true)) {
    if (file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) {
        throw new RuntimeException('Cannot write the semantic value schema.');
    }
    fwrite(STDOUT, 'Wrote ' . count($schema) . " value definitions; review every changed retained field.\n");
    exit(0);
}
$expected = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
if ($schema !== $expected) {
    foreach (array_unique([...array_keys($expected), ...array_keys($schema)]) as $class) {
        if (($schema[$class] ?? null) !== ($expected[$class] ?? null)) {
            fwrite(STDERR, "Unreviewed retained-field schema: $class\n");
        }
    }
    exit(1);
}
fwrite(STDOUT, 'Checked ' . count($schema) . " semantic value definitions.\n");
