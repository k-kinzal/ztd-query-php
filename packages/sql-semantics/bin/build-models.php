#!/usr/bin/env php
<?php

declare(strict_types=1);

require $_composer_autoload_path ?? dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/model-code.php';
require __DIR__ . '/model-grammar.php';
require __DIR__ . '/model-lexical.php';
require __DIR__ . '/model-contracts.php';

use SqlParser\Resource\SourceFetcher;
use SqlParser\Resource\VersionRegistry;

/**
 * Compiles SQL value classes from every shipped language release.
 *
 * A value stores only the arguments of its SQL form. Fixed SQL is compiled into
 * methods, finite choices become enums, and forwarding productions disappear.
 * The separate construction maps are used only while lowering a parser result.
 */

$options = getopt('', ['dialect:', 'output:', 'check', 'help']);
$dialect = $options['dialect'] ?? null;
$destination = $options['output'] ?? null;
if (isset($options['help']) || !is_string($dialect) || !in_array($dialect, ['mysql', 'postgresql', 'sqlite'], true) || !is_string($destination) || $destination === '') {
    fwrite(isset($options['help']) ? STDOUT : STDERR, "Usage: build-models.php --dialect=mysql|postgresql|sqlite --output=resources [--check]\nPaths are relative to the calling package; temporary files use build/.\n");
    exit(isset($options['help']) ? 0 : 2);
}
$workingDirectory = getcwd();
if ($workingDirectory === false) {
    throw new RuntimeException('Cannot resolve the calling package directory');
}
$registry = new VersionRegistry();
$fetcher = new SourceFetcher($workingDirectory . '/build/sources');
$versions = $registry->names($dialect);
$directory = $workingDirectory . '/build/model-resources/' . $dialect;
foreach (resourceFiles($directory) as $path) {
    unlink($path);
}
if (!is_dir($directory . '/mapping')) {
    mkdir($directory . '/mapping', 0777, true);
}
$releases = [];
$parents = [];
$roleNames = [];
$patterns = [];
$bindings = [];
$entryRules = [];
foreach ($versions as $version) {
    [$dialect, $url, $reader] = source($version);
    $grammar = $reader->read($fetcher->fetch($url));
    $entryRules[$dialect][] = $grammar->symbols->name($grammar->startSymbol());
    $formSet = forms($grammar, $version);
    $lexical = terminalPatterns($grammar, $version, $dialect);
    foreach (bindingContracts($grammar, $formSet) as $name => $contract) {
        $bindings[$name][$version] = $contract;
    }
    $releases[$version] = [$dialect, $formSet];
    foreach ($formSet as $name => $alternatives) {
        $roleNames[$dialect][$name] = true;
        foreach ($alternatives as $symbols) {
            foreach ($symbols as $symbol) {
                if ($symbol['terminal'] && $symbol['fixed'] === null) {
                    $pattern = $lexical[$symbol['name']] ?? null;
                    if ($pattern === null) {
                        throw new RuntimeException('Missing lexical spelling invariant: ' . $version . ':' . $symbol['name']);
                    }
                    $patterns[$symbol['name']][] = $pattern;
                }
            }
            if (count($symbols) === 1 && !$symbols[0]['terminal']) {
                $parents[$dialect][$symbols[0]['name']][] = $name;
            }
        }
    }
    fwrite(STDOUT, "Read {$version}\n");
}
foreach ($roleNames as $dialect => $names) {
    foreach (array_keys($names) as $name) {
        $interface = modelName($name) . 'Form';
        $fqcn = 'SqlSemantics\\Statement\\Model\\' . $dialect . '\\Role\\' . $interface;
        writeModel($directory, $dialect, 'Role', $interface, modelDoc($name, $fqcn) . "interface {$interface} extends \\SqlSemantics\\Statement\\Element\n{\n}");
    }
}
foreach ($releases as $version => [$dialect, $formSet]) {
    $mapping = [];
    foreach ($formSet as $name => $alternatives) {
        $interfaces = roles($name, $dialect, $parents[$dialect] ?? [], $entryRules[$dialect]);
        $choices = [];
        foreach ($alternatives as $ordinal => $symbols) {
            $pieces = array_column($symbols, 'fixed');
            if (!in_array(null, $pieces, true)) {
                $choices[$ordinal] = implode(' ', array_filter($pieces, static fn (string $text): bool => $text !== ''));
            }
        }
        if (count($choices) === count($alternatives)) {
            $mapping[$name] = choiceEnum($directory, $dialect, $name, $choices, $interfaces);
            continue;
        }
        foreach ($alternatives as $ordinal => $symbols) {
            $mapping[$name][$ordinal] = count($symbols) === 1 && !$symbols[0]['terminal']
                ? ['forward' => 0]
                : valueClass($directory, $dialect, $name, $symbols, $interfaces, $bindings);
        }
    }
    $code = preg_replace('/[ \t]+$/m', '', var_export($mapping, true));
    file_put_contents($directory . '/mapping/' . $version . '.php', "<?php\n\ndeclare(strict_types=1);\n\n/** Generated construction recipes; never retained by a Statement. */\nreturn new \\SqlSemantics\\Core\\Analysis\\ValueReader(" . $code . ");\n");
    fwrite(STDOUT, "Wrote {$version}\n");
}
writeContracts($directory, $dialect, $patterns, $bindings);
exit(publishModels($directory, $destination, isset($options['check'])));
