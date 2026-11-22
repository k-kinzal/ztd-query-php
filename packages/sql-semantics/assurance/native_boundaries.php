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
        $file = __DIR__ . '/sqlite-grouped-boundaries-v1.json';
        requireShape(hash_file('sha256', $file) === 'd5798628db61bbf32d8bf067c1753ca70835384d3649a8e26396e9882ad7297f', 'SQLite grouping boundary contract differs from the recorded revision');
    } else {
        $file = __DIR__ . '/native-boundaries-v11.json';
        requireShape(hash_file('sha256', $file) === 'f86d0da10d98d5f0f15af2faa2fd9730429d9d3612f07a6fe03575618bfaab98', 'Native boundary contract differs from the recorded revision');
    }

    $rules = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['rules'];

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
    foreach (['Model\\Diagnostic\\Check', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Binding\\Parameter', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\SelectBounds', 'Ir\\Ordering\\Order', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate', 'Statement\\Expression\\Grouped'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
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
