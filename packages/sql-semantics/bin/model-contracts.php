<?php

declare(strict_types=1);

use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;

/**
 * Retains expression binding facts needed by structural construction assertions.
 *
 * @param array<string, array<int, list<array{name: string, terminal: bool, fixed: ?string, identifier?: bool}>>> $forms
 * @return array<string, array{power: int, operands: array<int, int>}>
 */
function bindingContracts(Grammar $grammar, array $forms): array
{
    $contracts = [];
    $expressions = ['expr', 'a_expr', 'b_expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr'];
    foreach ($grammar->rules as $rule) {
        $name = $grammar->symbols->name($rule->lhs);
        $symbols = $forms[$name][$rule->ordinal] ?? [];
        $precedence = $grammar->rulePrecedence($rule);
        if (!in_array($name, $expressions, true) || $precedence === null || count($symbols) < 2) {
            continue;
        }
        $operands = [];
        foreach ($symbols as $index => $symbol) {
            if ($symbol['terminal'] || !in_array($symbol['name'], $expressions, true)) {
                continue;
            }
            if ($index !== 0 && $index !== count($symbols) - 1) {
                continue;
            }
            $equal = $index === 0 ? $precedence->associativity === Associativity::Left : $precedence->associativity === Associativity::Right;
            $operands[$index] = $precedence->level + ($equal ? 0 : 1);
        }
        if ($operands !== []) {
            $contracts[valueName($name, $symbols)] = ['power' => $precedence->level, 'operands' => $operands];
        }
    }

    return $contracts;
}

/**
 * Writes parser-independent facts used to state the generated value invariants.
 *
 * @param array<string, list<string>> $patterns
 * @param array<string, array<string, array{power: int, operands: array<int, int>}>> $bindings
 */
function writeContracts(string $directory, string $dialect, array $patterns, array $bindings): void
{
    $classes = [];
    foreach (['Value', 'Choice'] as $group) {
        foreach (glob($directory . '/models/' . $group . '/*.php') ?: [] as $file) {
            $classes['SqlSemantics\\Statement\\Model\\' . $dialect . '\\' . $group . '\\' . basename($file, '.php')] = true;
        }
    }
    ksort($classes);
    $spellings = [];
    foreach ($patterns as $terminal => $alternatives) {
        $spellings[$terminal] = '~\\A(?:' . implode('|', array_unique($alternatives)) . ')\\z~isD';
    }
    ksort($spellings);
    $powers = [];
    foreach ($bindings as $name => $versions) {
        $fqcn = 'SqlSemantics\\Statement\\Model\\' . $dialect . '\\Value\\' . $name;
        $powers[$fqcn] = array_map(static fn (array $contract): int => $contract['power'], $versions);
    }
    ksort($powers);
    $body = "/**\n * Immutable model membership, lexical domains and operand binding strengths.\n *\n * @visibility SqlSemantics\n */\nfinal class Contracts\n{\n";
    $types = ['ELEMENTS' => 'array<class-string<\\SqlSemantics\\Statement\\Element>, true>', 'SPELLINGS' => 'array<string, string>', 'BINDING_POWERS' => 'array<class-string<\\SqlSemantics\\Statement\\Element>, array<string, int>>'];
    foreach (['ELEMENTS' => $classes, 'SPELLINGS' => $spellings, 'BINDING_POWERS' => $powers] as $name => $values) {
        $export = preg_replace('/[ \t]+$/m', '', var_export($values, true));
        $body .= "    /**\n     * Generated construction facts; no parser is consulted by a value.\n     *\n     * @var {$types[$name]}\n     */\n    public const {$name} = " . $export . ";\n";
    }
    $body .= "\n    /**\n     * Answers whether a child belongs to this generated immutable vocabulary.\n     */\n    public static function contains(\\SqlSemantics\\Statement\\Element \$element): bool\n    {\n        return isset(self::ELEMENTS[\$element::class]);\n    }\n";
    writeModel($directory, $dialect, 'Contract', 'Contracts', $body . '}');
}

/**
 * Generates one typed copy method for each data-bearing constructor argument.
 *
 * @param array<string, string> $types
 */
function copyMethods(array $types): string
{
    $methods = '';
    foreach ($types as $field => $type) {
        $arguments = [];
        foreach (array_keys($types) as $other) {
            $arguments[] = $other === $field ? '$' . $field : '$this->' . $other;
        }
        $methods .= "\n\n    /**\n     * Returns a copy with a new {$field}, preserving every other field.\n     */\n    public function with" . ucfirst($field) . "({$type} \${$field}): self\n    {\n        return new self(" . implode(', ', $arguments) . ");\n    }";
    }

    return $methods;
}
