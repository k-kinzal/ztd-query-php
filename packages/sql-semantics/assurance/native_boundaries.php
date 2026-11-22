<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

function nativeBoundaryContract(string $dialect): array
{
    if ($dialect === 'sqlite') {
        $file = __DIR__ . '/sqlite-grouped-boundaries-v2.json';
        requireShape(hash_file('sha256', $file) === '0d054f583a15cc1ccc76a45d79b3cf2420279d8b26f51d112f4ec6bf022285e7', 'SQLite grouping boundary contract differs from the recorded revision');
    } else {
        $file = __DIR__ . '/native-boundaries-v13.json';
        requireShape(hash_file('sha256', $file) === '07a00e74fa719f98db2c26bb83b399336a62674ecf4604e9d3bfb96eee9099d3', 'Native boundary contract differs from the recorded revision');
    }

    $rules = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['rules'];

    if ($dialect === 'sqlite') {
        $extension = __DIR__ . '/sqlite-wildcard-boundaries-v1.json';
        requireShape(hash_file('sha256', $extension) === 'df1e85f4672df520cfbaed5e39260a25324c8c95cc02a38c94e1fc8baa847a1d', 'SQLite wildcard boundary contract differs from its recorded revision');
        $additional = json_decode(file_get_contents($extension), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape(array_intersect_key($additional, $rules) === [], 'SQLite wildcard cannot replace a fixed boundary');
        $rules += $additional;
        $insert = __DIR__ . '/sqlite-insert-query-boundaries-v1.json';
        requireShape(hash_file('sha256', $insert) === '3e049f960236f3ff9b883fc24b161741f7c70b7655e3462e09d5b99be1b38a6d', 'SQLite INSERT boundary contract differs from the recorded revision');
        $additional = json_decode(file_get_contents($insert), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape(array_intersect_key($additional, $rules) === [], 'SQLite query INSERT cannot replace a fixed boundary');
        $rules += $additional;
        $choice = __DIR__ . '/sqlite-case-boundaries-v1.json';
        requireShape(hash_file('sha256', $choice) === 'f632bc623433c75ed8195410d97bfe3a63886cb87cf0b2300f396ee702ded46a', 'SQLite CASE boundary contract differs from the recorded revision');
        $additional = json_decode(file_get_contents($choice), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape(array_intersect_key($additional, $rules) === [], 'SQLite CASE cannot replace a fixed boundary');
        $rules += $additional;
    } else {
        $extension = __DIR__ . '/native-insert-query-boundaries-v2.json';
        requireShape(
            hash_file('sha256', $extension) === '527e7d1284a409e72b022a14f1280ee2d7ed1cc738b47b5d61df3a72e9cc8ee2',
            'Native query INSERT boundary contract differs from the recorded revision'
        );
        $additional = json_decode(file_get_contents($extension), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape(array_intersect_key($additional, $rules) === [], 'Native query INSERT cannot replace a fixed boundary');
        $rules += $additional;
    }

    return array_filter($rules, static fn (array $rule): bool => !isset($rule['dialects']) || in_array($dialect, $rule['dialects'], true));
}

function checkNativeBoundaries(
    ClassLike $class,
    string $role,
    string $platform,
    string $dialect,
    array $rules,
    Parser $parser,
    NodeTraverser $traverser,
    Standard $printer,
): array {
    $imports = '';
    foreach (['Model\\Diagnostic\\Check', 'Model\\Diagnostic\\InvalidConstruction', 'Ir\\Identifier', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Rows\\LocalSource', 'Ir\\Rows\\ValuesQuery', 'Ir\\Mutation\\InsertConflict', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\SelectBounds', 'Ir\\Ordering\\Order', 'Ir\\Ordering\\ResultSql', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate',  'Statement\\Expression\\Grouped', 'Statement\\Query\\SelectExpression', 'Ir\\ExpressionLowering',
        'Ir\\Binding\\ParameterLowering', 'Ir\\Ordering\\ProjectionLabel'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
    if ($dialect === 'sqlite') {
        foreach (['Ir\\Parameter', 'Ir\\Expression\\CaseExpression', 'Ir\\Expression\\CaseBranch', 'Ir\\Literal\\BlobLiteral',
            'Ir\\Literal\\HexIntegerLiteral', 'Ir\\Literal\\RealLiteral', 'Ir\\Literal\\TextLiteral', 'Ir\\Predicate\\Comparison', 'Ir\\Predicate\\Logical'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\Binding\\Parameter;' . "\n";
    }
    $imports .= 'use ' . $platform . '\\Statement\\Query\\Select as ParsedSelect;' . "\n";
    $imports .= 'use ' . $platform . '\\Statement\\Relation\\' . ($dialect === 'mysql' ? 'JoinedTable' : 'Join') . ' as ParsedJoin;' . "\n";
    $verified = [];
    foreach ($rules as $id => $rule) {
        [$type, $method] = explode('::', $id);
        if ($type !== $role) {
            continue;
        }
        $code = implode("\n", [...$rule['common'], ...($rule[$dialect] ?? []), ...($rule['tail'] ?? [])]);
        $parts = explode('/', $role);
        array_pop($parts);
        $namespace = implode('\\', [$platform, 'Ir', ...$parts]);
        $template = $traverser->traverse($parser->parse('<?php namespace ' . $namespace . ';' . $imports . $code));
        requireShape(count($template) === 1 && $template[0] instanceof Namespace_, "Invalid native boundary template $id");
        $statements = array_values(array_filter($template[0]->stmts, static fn ($node) => !$node instanceof Use_));
        $actual = $class->getMethod($method)?->stmts;
        requireShape($actual !== null && $printer->prettyPrint($actual) === $printer->prettyPrint($statements), "Native boundary differs in $id");
        $verified[] = $id;
    }

    return $verified;
}
