<?php

declare(strict_types=1);

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

function checkedDispatchType(Expr $condition, string $variable, string $prefix, string $method): string
{
    requireShape(
        $condition instanceof Expr\Instanceof_
        && $condition->expr instanceof Expr\Variable && $condition->expr->name === $variable
        && $condition->class instanceof Name\FullyQualified && str_starts_with($condition->class->toString(), $prefix),
        "Unexpected typed condition in scalar dispatch $method"
    );

    return substr($condition->class->toString(), strlen($prefix));
}

function checkedScalarDispatch(Stmt\ClassLike $class, string $name, string $prefix, ?array $target = null): array
{
    $method = $class->getMethod($name);
    $parameter = $method?->params[0] ?? null;
    requireShape($method !== null && $parameter !== null && $parameter->var instanceof Expr\Variable
        && is_string($parameter->var->name) && $parameter->type !== null, "Missing typed scalar dispatch $name");
    $variable = $parameter->var->name;
    $domain = types($parameter->type, $prefix, 'SelectSql');
    $body = $method->stmts ?? [];
    $tail = array_pop($body);
    requireShape($tail instanceof Stmt\Return_ && $tail->expr !== null, "Missing terminal scalar dispatch $name");
    $handled = [];
    foreach ($body as $branch) {
        requireShape(
            $branch instanceof Stmt\If_ && $branch->else === null && $branch->elseifs === []
            && ($branch->stmts[array_key_last($branch->stmts)] ?? null) instanceof Stmt\Return_,
            "Unexpected early return form in scalar dispatch $name"
        );
        $handled[] = checkedDispatchType($branch->cond, $variable, $prefix, $name);
    }
    $forwarded = [];
    if ($target === null) {
        $match = $tail->expr;
        requireShape($match instanceof Expr\Match_ && $match->cond instanceof Expr\ConstFetch
            && $match->cond->name->toLowerString() === 'true', "Missing explicit match in scalar dispatch $name");
        foreach ($match->arms as $arm) {
            requireShape($arm->conds !== null && $arm->conds !== [], "Unhandled fallback in scalar dispatch $name");
            foreach ($arm->conds as $condition) {
                $handled[] = checkedDispatchType($condition, $variable, $prefix, $name);
            }
        }
    } else {
        $call = $tail->expr;
        $value = $call instanceof Expr\MethodCall ? ($call->args[0]->value ?? null) : null;
        requireShape($call instanceof Expr\MethodCall && $call->var instanceof Expr\Variable && $call->var->name === 'this'
            && $call->name instanceof Identifier && $call->name->toString() === 'expression'
            && $value instanceof Expr\Variable && $value->name === $variable, "Unexpected scalar delegation in $name");
        $forwarded = array_values(array_diff($domain, $handled));
        requireShape(array_diff($forwarded, $target['domain']) === [], "Scalar delegation loses a typed role in $name");
    }
    sort($handled);
    sort($forwarded);
    $partition = [...$handled, ...$forwarded];
    sort($partition);
    requireShape($partition === $domain, "Incomplete scalar dispatch in $name");

    return ['domain' => $domain, 'handled' => $handled, 'forwarded' => $forwarded];
}
