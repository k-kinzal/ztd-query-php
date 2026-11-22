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
    $records = match ($dialect) {
        'sqlite' => [
            ['sqlite-query-scope-boundaries-v1.json', '8deaf1505b4e3dac0dcf73c7b4149862b9145530824cb89b08933b3599c68906'],
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v1.json', 'df1e85f4672df520cfbaed5e39260a25324c8c95cc02a38c94e1fc8baa847a1d'],
            ['sqlite-insert-slots-boundaries-v1.json', 'cde9f0df0d6f1ca8ff20ddac140fd96577cf456c2facfd00e809f5d89a717d9e'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v11.json', 'c0774a7a164324280a42032b79ea1d4c58e22dd4eef0bdbe098edcecc0a8a949'],
            ['sqlite-default-boundaries-v1.json', 'c2f126dd959b203343c615191d06ddc04a97d085534da70b0137ab57f819600a'],
            ['sqlite-mutation-returning-boundaries-v1.json', '551ad79f5e71906743b7e256a7f492034d5f27e4f2fee852a5241138410ea07f'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-mutation-returning-boundaries-v2.json', '082d7dd3af450a824ec217de545754e2f034eb33027dbb342456531b31dcefc3'],
            ] : []),
            ['native-boundaries-v18.json', '6a047f86445270675ec3d1251cc1e5639008ce1f52cd34ec4d3b4735090bddf8'],
            ['native-insert-query-boundaries-v4.json', '67796e3b509a778308dcdf70c04ebcf7d455e9014faac77c426f6ee105290373'],
        ],
    };
    $rules = [];
    foreach ($records as [$name, $digest]) {
        $path = __DIR__ . '/' . $name;
        requireShape(is_file($path) && hash_file('sha256', $path) === $digest, "Boundary contract differs from its recorded revision: $name");
        $additional = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape($additional !== [] && array_intersect_key($additional, $rules) === [], "Boundary contract has no distinct rules: $name");
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
    foreach (['Model\\Diagnostic\\Check', 'Model\\Diagnostic\\InvalidConstruction', 'Ir\\Identifier', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Rows\\LocalSource', 'Ir\\Rows\\ValuesQuery', 'Ir\\Mutation\\InsertConflict', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\Ordering\\Order', 'Ir\\Ordering\\ResultSql', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate',  'Statement\\Expression\\Grouped', 'Statement\\Query\\SelectExpression', 'Ir\\ExpressionLowering',
        'Ir\\Binding\\ParameterLowering', 'Ir\\Ordering\\ProjectionLabel'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
    if ($dialect === 'sqlite') {
        $imports .= 'use ' . $platform . '\\Ir\\From\\SelectBounds;' . "\n";
        $imports .= 'use ' . $platform . '\\Ir\\Rows\\RecursiveDefinition;' . "\n";
        $imports .= 'use ' . $platform . '\\Platform;' . "\n";
        foreach (['Ir\\Predicate\\Pattern\\PatternTest', 'Ir\\Predicate\\Pattern\\PatternKind', 'Statement\\Expression\\Operator\\PatternOperator',
            'Statement\\Expression\\Operator\\PatternMatch as ParsedPattern'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        foreach (['Ir\\Parameter', 'Ir\\Collation', 'Ir\\Expression\\BinaryOperatorLowering', 'Ir\\ArithmeticOperator', 'Statement\\Expression\\Operator\\BinaryOperator', 'Ir\\Predicate\\ComparisonOperator', 'Statement\\Expression\\Operator\\NullTestForm', 'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\BetweenTest', 'Ir\\Predicate\\MembershipTest', 'Ir\\Predicate\\MembershipItem', 'Ir\\Predicate\\FoldedMembership', 'Ir\\Predicate\\FoldedMembershipLowering', 'Model\\Rendering\\Output', 'Model\\Rendering\\PieceKind', 'Rendering\\Codec', 'Statement\\Expression\\TruthWord',
            'Statement\\Expression\\FunctionCall', 'Statement\\Expression\\Operator\\Binary', 'Ir\\Expression\\CaseExpression', 'Ir\\Expression\\CaseBranch', 'Ir\\Expression\\CastExpression', 'Ir\\Expression\\CollatedExpression', 'Ir\\Expression\\Concatenation', 'Statement\\Expression\\Collate as ParsedCollate', 'Ir\\Expression\\CastTarget', 'Ir\\Expression\\UnaryExpression', 'Ir\\Expression\\UnaryKind',
            'Ir\\Literal\\NumericLiteralLowering', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
            'Statement\\Expression\\Literal\\IntegerLiteral as ParsedInteger', 'Statement\\Expression\\Literal\\HexLiteral as ParsedHex',
            'Statement\\Expression\\Literal\\RealLiteral as ParsedReal', 'Statement\\Type\\Affinity', 'Ir\\Literal\\BlobLiteral',
            'Ir\\Literal\\HexIntegerLiteral', 'Ir\\Literal\\RealLiteral', 'Ir\\Literal\\TextLiteral', 'Ir\\Predicate\\Comparison', 'Ir\\Predicate\\Logical', 'Ir\\Predicate\\ExpressionScope', 'Ir\\Schema\\TableColumn',
            'Ir\\Schema\\DeclaredDatatypeName', 'Ir\\Schema\\DefaultLowering', 'Ir\\Mutation\\ReturnedColumn', 'Ir\\Mutation\\DefaultRow',
            'Ir\\Mutation\\UpdateConflict', 'Statement\\Expression\\ColumnUse', 'Statement\\Expression\\DoubleQuotedWord', 'Statement\\Query\\TableStar', 'Ir\\From\\SourceDeclarations', 'Statement\\Schema\\Column\\NotNull',
            'Statement\\Schema\\Column\\DefaultLiteral', 'Statement\\Schema\\Column\\DefaultExpression', 'Statement\\Schema\\Column\\DefaultWord', 'Statement\\Mutation\\MutationTarget'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        $imports .= 'use ' . $platform . '\\Model\\Statement\\Identifier\\Comparison as NameComparison;' . "\n";
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\SelectBounds;' . "\n";
        if ($dialect === 'postgres') {
            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
            foreach (['Ir\\Expression\\IntegerCase', 'Ir\\Expression\\IntegerCaseArm', 'Ir\\Expression\\IntegerCaseLowering',
                'Ir\\Predicate\\IntegerRange', 'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\IntegerRangeOperandLowering',
                'Statement\\Expression\\BinaryOperation', 'Statement\\Expression\\ColumnReference as ParsedColumn',
                'Statement\\Expression\\Operator\\Between as ParsedRange', 'Statement\\Expression\\Operator\\UnaryOperation',
                'Statement\\Expression\\Constructor\\CaseExpression as ParsedCase', 'Statement\\Literal\\BooleanLiteral as ParsedBoolean',
                'Statement\\Literal\\Constant', 'Statement\\Literal\\IntegerConstant', 'Statement\\Literal\\NumericConstant', 'Ir\\Predicate\\PredicateLowering',
                'Ir\\SourceBinding', 'Ir\\Mutation\\ReturningSql', 'Ir\\Mutation\\ReturningLayout', 'Ir\\Mutation\\ReturningLowering',
                'Ir\\Mutation\\Insert\\Destination', 'Ir\\Mutation\\InsertTargetBinding', 'Ir\\Mutation\\InsertCell',
                'Ir\\Mutation\\ReturnedColumn', 'Model\\Contract\\ParameterStyle', 'Statement\\Manipulation\\CurrentOf',
                'Statement\\Manipulation\\Assignment\\Assignment as ParsedAssignment',
                'Statement\\Expression\\ColumnReference as ParsedReturnColumn',
                'Statement\\Expression\\Cast', 'Statement\\Literal\\NullLiteral as ParsedNull', 'Statement\\Expression\\BinaryOperation as ParsedComparison',
                'Statement\\Expression\\Operator\\BooleanOperation as ParsedLogical', 'Statement\\Expression\\Operator\\BooleanOperator',
                'Statement\\Expression\\Operator\\Negation as ParsedNot', 'Statement\\Expression\\Predicate\\NullTest as ParsedNullTest',
                'Model\\Statement\\Type\\Known', 'Statement\\Type\\Descriptor\\Builtin', 'Ir\\Rows\\Compound', 'Ir\\Rows\\ResultKind'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
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
