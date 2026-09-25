#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/model-code.php';

use SqlParser\Compiler\BisonGrammarReader;
use SqlParser\Compiler\LemonGrammarReader;
use SqlParser\Grammar\Grammar;
use SqlParser\Resource\SourceFetcher;
use SqlParser\Resource\VersionRegistry;

/**
 * Compiles SQL value classes from every shipped language release.
 *
 * A value stores only the arguments of its SQL form. Fixed SQL is compiled into
 * methods, finite choices become enums, and forwarding productions disappear.
 * The separate construction maps are used only while lowering a parser result.
 */

/**
 * Finds invariant spellings. Lexical values and spelling choices become fields.
 *
 * @return array<string, string>
 */
function fixedSpellings(string $version): array
{
    $data = require dirname(__DIR__) . '/vendor/k-kinzal/sql-parser/resources/keywords/' . $version . '.php';
    $spellings = [];
    foreach ($data as $group) {
        foreach ($group as $word => $symbol) {
            $spellings[$symbol][$word] = true;
        }
    }
    $fixed = [];
    foreach ($spellings as $symbol => $words) {
        if (count($words) === 1) {
            $fixed[$symbol] = array_key_first($words);
        }
    }
    if (str_starts_with($version, 'sqlite-')) {
        $fixed += ['LP' => '(', 'RP' => ')', 'COMMA' => ',', 'DOT' => '.', 'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%', 'BITAND' => '&', 'BITOR' => '|', 'LSHIFT' => '<<', 'RSHIFT' => '>>', 'BITNOT' => '~', 'CONCAT' => '||'];
        unset($fixed['ID'], $fixed['STRING'], $fixed['ANY']);
    }
    $fixed['END_OF_INPUT'] = '';
    $fixed['$end'] = '';

    return $fixed;
}

/**
 * Reads the data-bearing positions; hidden parser actions are absent from values.
 *
 * @return array<string, array<int, list<array{name: string, terminal: bool, fixed: ?string, identifier?: bool}>>>
 */
function forms(Grammar $grammar, string $version): array
{
    $fixed = fixedSpellings($version);
    $hidden = [];
    foreach ($grammar->rules as $rule) {
        if ($rule->hidden) {
            $hidden[$rule->lhs] = true;
        }
    }
    $forms = [];
    foreach ($grammar->rules as $rule) {
        if ($rule->hidden || $rule->index === 0) {
            continue;
        }
        $symbols = [];
        foreach ($rule->rhs as $id) {
            if (isset($hidden[$id])) {
                continue;
            }
            $name = $grammar->symbols->name($id);
            $terminal = $grammar->symbols->isTerminal($id);
            $word = $terminal ? ($fixed[$name] ?? (strlen($name) === 1 ? $name : null)) : null;
            if (isset($grammar->tokenClasses[$id]) || $id === $grammar->wildcard) {
                $word = null;
            }
            $symbols[] = ['name' => $name, 'terminal' => $terminal, 'fixed' => $word];
        }
        $forms[$grammar->symbols->name($rule->lhs)][$rule->ordinal] = $symbols;
    }

    return identifierForms($forms, $version);
}

/**
 * Keywords used as names are identifier data, including their original spelling.
 * Walks only the language's name productions, never expressions or statements.
 *
 * @param array<string, array<int, list<array{name: string, terminal: bool, fixed: ?string, identifier?: bool}>>> $forms
 * @return array<string, array<int, list<array{name: string, terminal: bool, fixed: ?string, identifier?: bool}>>>
 */
function identifierForms(array $forms, string $version): array
{
    $pending = str_starts_with($version, 'mysql-')
        ? ['ident', 'IDENT_sys', 'ident_or_text', 'label_ident', 'role_ident']
        : (str_starts_with($version, 'pg-') ? ['ColId', 'ColLabel', 'BareColLabel', 'type_function_name', 'NonReservedWord'] : ['nm', 'idj']);
    $seen = [];
    while ($pending !== []) {
        $name = array_pop($pending);
        if (isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        foreach ($forms[$name] ?? [] as $ordinal => $symbols) {
            foreach ($symbols as $index => $symbol) {
                if (!$symbol['terminal']) {
                    $pending[] = $symbol['name'];
                    continue;
                }
                $forms[$name][$ordinal][$index]['fixed'] = null;
                $forms[$name][$ordinal][$index]['identifier'] = true;
            }
        }
    }

    return $forms;
}

/**
 * Selects the upstream grammar source corresponding to the parser's release.
 *
 * @return array{string, string, BisonGrammarReader|LemonGrammarReader}
 */
function source(string $version): array
{
    if (str_starts_with($version, 'mysql-')) {
        return ['MySql', 'https://raw.githubusercontent.com/mysql/mysql-server/refs/tags/' . $version . '/sql/sql_yacc.yy', new BisonGrammarReader()];
    }
    if (str_starts_with($version, 'pg-')) {
        $tag = 'REL_' . str_replace('.', '_', substr($version, 3));

        return ['PostgreSql', 'https://raw.githubusercontent.com/postgres/postgres/refs/tags/' . $tag . '/src/backend/parser/gram.y', new BisonGrammarReader()];
    }

    return ['Sqlite', 'https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/version-' . substr($version, 7) . '/src/parse.y', new LemonGrammarReader()];
}

$registry = new VersionRegistry();
$fetcher = new SourceFetcher(dirname(__DIR__) . '/build/sources');
$versions = [...$registry->names('mysql'), ...$registry->names('postgresql'), ...$registry->names('sqlite')];
$directory = dirname(__DIR__) . '/build/model-resources';
foreach (resourceFiles($directory) as $path) {
    unlink($path);
}
if (!is_dir($directory . '/mapping')) {
    mkdir($directory . '/mapping', 0777, true);
}
$releases = [];
$parents = [];
$roleNames = [];
foreach ($versions as $version) {
    [$dialect, $url, $reader] = source($version);
    $formSet = forms($reader->read($fetcher->fetch($url)), $version);
    $releases[$version] = [$dialect, $formSet];
    foreach ($formSet as $name => $alternatives) {
        $roleNames[$dialect][$name] = true;
        foreach ($alternatives as $symbols) {
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
        $interfaces = roles($name, $dialect, $parents[$dialect] ?? []);
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
                : valueClass($directory, $dialect, $name, $symbols, $interfaces);
        }
    }
    $code = preg_replace('/[ \t]+$/m', '', var_export($mapping, true));
    file_put_contents($directory . '/mapping/' . $version . '.php', "<?php\n\ndeclare(strict_types=1);\n\n/** Generated construction recipes; never retained by a Statement. */\nreturn new \\SqlSemantics\\Core\\Analysis\\ValueReader(" . $code . ");\n");
    fwrite(STDOUT, "Wrote {$version}\n");
}
exit(publishModels($directory, dirname(__DIR__) . '/resources', in_array('--check', $argv, true)));
