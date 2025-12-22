#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Analyze PG and SQLite grammars to enumerate all SQL statement types.
 * Used for Task #17: spec redefinition based on grammar analysis.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Terminal;

function loadGrammar(string $cacheFile): Grammar
{
    return Grammar::loadFromFile($cacheFile);
}

function analyzeStmtAlternatives(Grammar $grammar, string $stmtRule): array
{
    $rule = $grammar->ruleMap[$stmtRule] ?? null;
    if ($rule === null) {
        return [];
    }

    $results = [];
    foreach ($rule->alternatives as $alt) {
        $symbols = [];
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof Terminal) {
                $symbols[] = $sym->value;
            } elseif ($sym instanceof NonTerminal) {
                $symbols[] = '<' . $sym->value . '>';
            }
        }
        // Identify the statement type from the first non-terminal or keywords
        $firstNonTerminal = null;
        $firstTerminals = [];
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof NonTerminal) {
                $firstNonTerminal = $sym->value;
                break;
            }
            if ($sym instanceof Terminal) {
                $firstTerminals[] = $sym->value;
            }
        }

        $label = $firstNonTerminal ?? implode(' ', $firstTerminals);
        $results[] = [
            'label' => $label,
            'symbols' => implode(' ', $symbols),
            'first_nt' => $firstNonTerminal,
            'first_terminals' => $firstTerminals,
        ];
    }

    return $results;
}

// --- PostgreSQL ---
echo "=== PostgreSQL (pg-17.2) ===\n\n";
$pgGrammar = loadGrammar(__DIR__ . '/../resources/ast/pg-17.2.php');

// PG's top-level is parse_toplevel -> stmtmulti -> stmtmulti ';' toplevel_stmt
// toplevel_stmt -> stmt | ...
// stmt has all the alternatives
echo "--- stmt rule alternatives ---\n";
$pgStmts = analyzeStmtAlternatives($pgGrammar, 'stmt');
$pgStmtNames = [];
foreach ($pgStmts as $s) {
    echo sprintf("  %-40s => %s\n", $s['label'], $s['symbols']);
    $pgStmtNames[] = $s['label'];
}
echo "\nTotal PG statement types: " . count($pgStmts) . "\n\n";

// Group by category
$pgCategories = [];
foreach ($pgStmtNames as $name) {
    if (str_contains($name, 'Select') || str_contains($name, 'select')) {
        $pgCategories['DML'][] = $name;
    } elseif (str_contains($name, 'Insert') || str_contains($name, 'insert')) {
        $pgCategories['DML'][] = $name;
    } elseif (str_contains($name, 'Update') || str_contains($name, 'update')) {
        $pgCategories['DML'][] = $name;
    } elseif (str_contains($name, 'Delete') || str_contains($name, 'delete')) {
        $pgCategories['DML'][] = $name;
    } elseif (str_contains($name, 'Merge') || str_contains($name, 'merge')) {
        $pgCategories['DML'][] = $name;
    } elseif (str_contains($name, 'Create') || str_contains($name, 'create')) {
        $pgCategories['DDL'][] = $name;
    } elseif (str_contains($name, 'Alter') || str_contains($name, 'alter')) {
        $pgCategories['DDL'][] = $name;
    } elseif (str_contains($name, 'Drop') || str_contains($name, 'drop')) {
        $pgCategories['DDL'][] = $name;
    } elseif (str_contains($name, 'Index') || str_contains($name, 'index')) {
        $pgCategories['DDL'][] = $name;
    } elseif (str_contains($name, 'Transaction') || str_contains($name, 'Prepare')) {
        $pgCategories['TCL'][] = $name;
    } elseif (str_contains($name, 'Grant') || str_contains($name, 'Revoke') || str_contains($name, 'Role') || str_contains($name, 'Reassign')) {
        $pgCategories['DCL'][] = $name;
    } else {
        $pgCategories['Other'][] = $name;
    }
}

echo "--- PG Categories ---\n";
foreach ($pgCategories as $cat => $names) {
    echo "\n[$cat]\n";
    sort($names);
    foreach ($names as $n) {
        echo "  - $n\n";
    }
}

// --- SQLite ---
echo "\n\n=== SQLite (sqlite-3.47.2) ===\n\n";
$sqliteGrammar = loadGrammar(__DIR__ . '/../resources/ast/sqlite-3.47.2.php');

echo "--- cmd rule alternatives ---\n";
$sqliteStmts = analyzeStmtAlternatives($sqliteGrammar, 'cmd');
$sqliteStmtNames = [];
foreach ($sqliteStmts as $s) {
    echo sprintf("  %-40s => %s\n", $s['label'], $s['symbols']);
    if ($s['first_nt']) {
        $sqliteStmtNames[] = $s['first_nt'] . ' (' . implode(' ', $s['first_terminals']) . '...)';
    } else {
        $sqliteStmtNames[] = implode(' ', $s['first_terminals']);
    }
}
echo "\nTotal SQLite cmd alternatives: " . count($sqliteStmts) . "\n\n";

// Also check other top-level structures
echo "--- All rule names in SQLite grammar ---\n";
$sqliteRules = array_keys($sqliteGrammar->ruleMap);
sort($sqliteRules);
foreach ($sqliteRules as $rule) {
    $altCount = count($sqliteGrammar->ruleMap[$rule]->alternatives);
    echo "  $rule ($altCount alternatives)\n";
}

echo "\n--- All rule names in PG grammar ---\n";
$pgRules = array_keys($pgGrammar->ruleMap);
sort($pgRules);
foreach ($pgRules as $rule) {
    $altCount = count($pgGrammar->ruleMap[$rule]->alternatives);
    echo "  $rule ($altCount alternatives)\n";
}
