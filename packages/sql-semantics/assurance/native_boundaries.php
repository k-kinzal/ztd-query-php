<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

function nativeBoundaryContract(): array
{
    $file = __DIR__ . '/native-boundaries-v3.json';
    requireShape(hash_file('sha256', $file) === '6ab147587ae4fa1e7ada1c2b89a2b2fb0167f9bd65b29f7ddf60c38fe016378f', 'Native boundary contract differs from the recorded revision');

    return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['rules'];
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
    foreach (['Model\\Diagnostic\\Check', 'Ir\\ColumnReference', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
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
