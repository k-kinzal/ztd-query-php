<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\FunctionSignature;

/**
 * Resolves registered function names and overloads in the schema snapshot.
 *
 * @visibility SqlSemantics
 */
final class FunctionResolver
{
    /**
     * @param list<Expression> $arguments
     */
    public function resolve(Node $source, array $arguments, Scope $scope): ?FunctionSignature
    {
        $parts = [];
        foreach ($source->tokens() as $token) {
            if ($token->text === '(') {
                break;
            }
            if ($token->text !== '.') {
                $parts[] = $scope->identifiers->name($token);
            }
        }
        $name = array_pop($parts) ?? '';
        $namespace = $parts === [] ? null : implode('.', $parts);
        $schema = $scope->queries?->tables->schema;
        $functions = $schema->functions ?? \SqlSemantics\Schema\Functions\Builtins::forDialect($scope->identifiers->dialect);
        $candidates = array_values(array_filter($functions, static fn (FunctionSignature $function): bool => $scope->identifiers->equal($function->name, $name) && ($namespace === null ? ($function->schema === null || $function->schema === $schema?->defaultSchema) : ($function->schema === $namespace || ($namespace === 'pg_catalog' && $scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql && $function->schema === null)))));
        return $this->overload($candidates, $arguments, $source, $scope);
    }

    /**
     * @param list<FunctionSignature> $candidates
     * @param list<Expression> $arguments
     */
    public function overload(array $candidates, array $arguments, Node $source, Scope $scope): ?FunctionSignature
    {
        if ($candidates === []) {
            return null;
        }
        $best = [];
        $highest = -1;
        foreach ($candidates as $candidate) {
            $score = FunctionMatch::score($candidate, $arguments);
            if ($score === null) {
                continue;
            }
            if ($score > $highest) {
                $highest = $score;
                $best = [];
            }
            if ($score === $highest) {
                $best[] = $candidate;
            }
        }
        if (count($best) === 1) {
            return $best[0];
        }
        $arity = array_filter($candidates, static fn (FunctionSignature $candidate): bool => FunctionMatch::arity($candidate, count($arguments)));
        $reason = $best !== [] ? 'ambiguous-function' : ($arity === [] ? 'invalid-arity' : 'incompatible-arguments');
        $scope->diagnostics()->report($reason, 'Cannot resolve a unique function signature for ' . $candidates[0]->name, $source);
        return null;
    }
}
