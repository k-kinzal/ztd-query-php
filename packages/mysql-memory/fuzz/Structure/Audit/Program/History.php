<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Program;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Establishes stored-routine dependencies under their original referenced names.
 * Arguments and characteristics are never replaced. A later conversion or
 * declaration condition remains visible after the routine fixture is created.
 */
final class History
{
    /**
     * @param array<string, mixed> $before The retained missing-routine observation
     * @return list<M\Statement> Compared setup before repeating the original object
     */
    public function steps(M\Statement|Scalar $node, array $before, string $database): array
    {
        $error = $before['error'] ?? null;
        if (!is_array($error) || ($error[0] ?? null) !== 1305 || !is_string($error[2] ?? null)) {
            return [];
        }
        $target = $this->target($node, $error[2], $database);
        if ($target === null) {
            return [];
        }
        [$kind, $name, $arity] = $target;
        $schema = $name->schema ?? new Name($database === '' ? 'structure_fixture' : $database);
        $name = new QualifiedName($name->name, $schema);
        $parameters = [];
        for ($index = 0; $index < $arity; $index++) {
            $parameters[] = new M\Routine\Parameter(new Name('argument_' . $index), new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
        }
        $parameters = new M\Routine\ParameterList($parameters);
        $routine = $kind === M\Routine\ProgramKind::Procedure
            ? new M\Routine\CreateProcedure($name, $parameters, new M\Routine\Program\Block())
            : new M\Routine\CreateFunction($name, $parameters, new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation(new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('7')), [new M\Routine\Characteristic\Determinism(true)]));
        return [new M\Server\Database\CreateDatabase(true, $schema), new M\Utility\Explain\UseDatabase($schema), $routine];
    }

    /**
     * @return array{M\Routine\ProgramKind, QualifiedName, int}|null Original routine identity and parameter count
     */
    public function target(M\Statement|Scalar $node, string $message, string $database): ?array
    {
        if ($node instanceof M\Dml\ProcedureCall) {
            return [M\Routine\ProgramKind::Procedure, $node->procedure, count($node->arguments)];
        }
        if ($node instanceof M\Routine\AlterRoutine || ($node instanceof M\Routine\DropProgram && !$node->legacyName() && in_array($node->kind, [M\Routine\ProgramKind::Function, M\Routine\ProgramKind::Procedure], true))) {
            return [$node->kind, $node->name, 0];
        }
        if ($node instanceof M\Utility\Show\Program\ShowCreateProcedure || $node instanceof M\Utility\Show\Program\ShowProcedureCode) {
            return [M\Routine\ProgramKind::Procedure, $node->name, 0];
        }
        if ($node instanceof M\Utility\Show\Program\ShowCreateFunction || $node instanceof M\Utility\Show\Program\ShowFunctionCode) {
            return [M\Routine\ProgramKind::Function, $node->name, 0];
        }
        foreach ((new \Fuzz\Structure\Audit\Table\Inputs())->values($node) as $value) {
            if (!$value instanceof M\Call\FunctionCall || $value->named()) {
                continue;
            }
            $schema = $value->schema->value ?? $database;
            $identity = ($schema === '' ? '' : $schema . '.') . $value->name->value;
            if ($message === 'FUNCTION ' . $identity . ' does not exist') {
                return [M\Routine\ProgramKind::Function, new QualifiedName($value->name, $value->schema), count($value->arguments)];
            }
        }
        return null;
    }
}
