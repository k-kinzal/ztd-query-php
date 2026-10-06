<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ParallelSafety;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultTable;

/**
 * Reports routine definitions, parameter lists and option lists the server rejects.
 *
 * Rule: PG-ROUTINE-CHECK-001. Options: an attribute set twice is
 * conflicting (configuration settings may repeat); a procedure accepts no
 * null-input behavior, volatility, leakproofness, estimate, support function,
 * parallel mode or WINDOW; PARALLEL takes `safe`, `restricted` or `unsafe`.
 * Body: a routine needs exactly one of `AS` and an SQL-standard body; a
 * routine without a body has to name its language; an SQL-standard body is
 * for language `sql` only; only language `c` takes an object file and a
 * link symbol. Parameters: VARIADIC is the last input parameter;
 * only input parameters take default values and every input parameter after
 * one with a default value takes one too; a procedure's OUT parameter may
 * not follow a parameter with a default value; two input or two output
 * parameters (TABLE columns are output parameters) cannot share a name; a
 * TABLE function has no OUT or INOUT parameters; a function without RETURNS
 * needs an output parameter. Terminates: quadratic in the parameter count.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html,
 * `compute_function_attributes` and `interpret_function_parameter_list` in
 * `src/backend/commands/functioncmds.c` of PostgreSQL 17. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RoutineChecks
{
    /**
     * The attributes a procedure does not accept.
     */
    private const FUNCTION_ONLY = ['strict', 'volatility', 'leakproof', 'cost', 'rows', 'support', 'parallel', 'window'];

    /**
     * Reports repeated attributes, attributes a procedure does not accept and invalid parallel modes.
     *
     * @param list<RoutineOption> $options
     */
    public function options(array $options, bool $procedure, Derivation $derivation): void
    {
        $seen = [];
        foreach ($options as $option) {
            $setting = $option->setting();
            if ($setting !== null && isset($seen[$setting])) {
                $derivation->report(new RoutineProblem(RoutineProblemKind::ConflictingOptions));
            }
            if ($setting !== null) {
                $seen[$setting] = true;
            }
            if ($procedure && in_array($setting, self::FUNCTION_ONLY, true)) {
                $derivation->report(new RoutineProblem(RoutineProblemKind::ProcedureAttribute));
            }
            if ($option instanceof ParallelSafety && !$option->valid()) {
                $derivation->report(new RoutineProblem(RoutineProblemKind::ParallelLevel));
            }
        }
    }

    /**
     * Reports a missing, duplicated or misplaced body and a missing or wrong language.
     */
    public function body(CreateFunction $function, Derivation $derivation): void
    {
        $definition = false;
        $language = null;
        foreach ($function->options as $option) {
            $definition = $definition || $option instanceof RoutineDefinition;
            $language = $option instanceof RoutineLanguage ? $option : $language;
            $source = $option instanceof RoutineDefinition && $option->symbol !== null ? $option->source->language : null;
            if ($source !== null && $source->value !== 'c') {
                $derivation->report(new RoutineProblem(RoutineProblemKind::SingleDefinition, $source->value));
            }
        }
        $body = $function->body !== null;
        $problem = match (true) {
            !$definition && !$body => RoutineProblemKind::MissingBody,
            $definition && $body => RoutineProblemKind::DuplicateBody,
            $language === null && !$body => RoutineProblemKind::MissingLanguage,
            $body && $language !== null && $language->name() !== 'sql' => RoutineProblemKind::InlineBodyLanguage,
            default => null,
        };
        if ($problem !== null) {
            $derivation->report(new RoutineProblem($problem));
        }
    }

    /**
     * Reports parameter lists and results the server rejects.
     */
    public function parameters(CreateFunction $function, Derivation $derivation): void
    {
        $parameters = $function->parameters->parameters;
        $variadic = false;
        $defaults = false;
        $outputs = false;
        foreach ($parameters as $parameter) {
            $problem = $this->parameter($function, $parameter, $variadic, $defaults);
            if ($problem !== null) {
                $derivation->report(new RoutineProblem($problem));
            }
            $variadic = $variadic || $parameter->mode === ParameterMode::Variadic;
            $defaults = $defaults || $parameter->default !== null;
            $outputs = $outputs || $parameter->output();
        }
        if (!$function->procedure && $function->returns === null && !$outputs) {
            $derivation->report(new RoutineProblem(RoutineProblemKind::MissingResultType));
        }
        $this->names($function, $derivation);
    }

    /**
     * Answers the problem of one parameter given whether a VARIADIC parameter or a default value precedes it, or null.
     */
    public function parameter(CreateFunction $function, FunctionParameter $parameter, bool $variadic, bool $defaults): ?RoutineProblemKind
    {
        return match (true) {
            $parameter->input() && $variadic => RoutineProblemKind::VariadicNotLast,
            $parameter->default !== null && $parameter->mode === ParameterMode::Out => RoutineProblemKind::DefaultOnOutput,
            $parameter->input() && $defaults && $parameter->default === null => RoutineProblemKind::MissingDefault,
            $function->procedure && $defaults && $parameter->mode === ParameterMode::Out => RoutineProblemKind::ProcedureOutputAfterDefault,
            $function->returns instanceof ResultTable && $parameter->output() => RoutineProblemKind::OutputInTableFunction,
            default => null,
        };
    }

    /**
     * Reports two input or two output parameters with the same name.
     */
    public function names(CreateFunction $function, Derivation $derivation): void
    {
        $named = [];
        foreach ($function->parameters->parameters as $parameter) {
            if ($parameter->name !== null && $parameter->name->value !== '') {
                $named[] = [$parameter->name->value, $parameter->input(), $parameter->output()];
            }
        }
        foreach ($function->returns instanceof ResultTable ? $function->returns->columns : [] as $column) {
            $named[] = [$column->name->value, false, true];
        }
        foreach ($named as $index => [$name, $input, $output]) {
            foreach (array_slice($named, 0, $index) as [$earlier, $earlierInput, $earlierOutput]) {
                if ($earlier === $name && (($input && $earlierInput) || ($output && $earlierOutput))) {
                    $derivation->report(new RoutineProblem(RoutineProblemKind::DuplicateParameter, $name));
                    break;
                }
            }
        }
    }
}
