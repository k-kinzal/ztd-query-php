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
    $file = __DIR__ . '/native-boundaries-v6.json';
    requireShape(hash_file('sha256', $file) === 'd657703677fe872e13ee88848de7097adceab732b48dce8b71389c2f72ecc18d', 'Native boundary contract differs from the recorded revision');

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
    foreach (['Model\\Diagnostic\\Check', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Binding\\Parameter', 'Ir\\From\\Join', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
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
