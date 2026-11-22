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
            ['sqlite-conflict-boundaries-v2.json', '8d859e50ede03278918a73739153f01cb63d1d03e139cee95587e577bc63d168'],
            ['sqlite-query-scope-boundaries-v1.json', '8deaf1505b4e3dac0dcf73c7b4149862b9145530824cb89b08933b3599c68906'],
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v1.json', 'df1e85f4672df520cfbaed5e39260a25324c8c95cc02a38c94e1fc8baa847a1d'],
            ['sqlite-insert-slots-boundaries-v3.json', 'ff1868cce0bf4174b3bbadde618f58a9383fa73c70c37ab300d83962aac00af4'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v11.json', 'c0774a7a164324280a42032b79ea1d4c58e22dd4eef0bdbe098edcecc0a8a949'],
            ['sqlite-default-boundaries-v5.json', '0f8fa057e393b8af7154e4b7ccbba7c46110660cf6a024c8cc972bfdcedf9158'],
            ['sqlite-mutation-returning-boundaries-v2.json', 'bc18d0046658c381649211e67f76dbd4faaba16e37526e35eca14e1e6a9fd956'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-mutation-returning-boundaries-v2.json', '082d7dd3af450a824ec217de545754e2f034eb33027dbb342456531b31dcefc3'],
            ] : []),
            ['native-boundaries-v22.json', 'cc2657464c16a43dbeab4da65f78c7e36cee6026af78a786379a42079d0d94d0'],
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
        foreach (['Ir\\SourceBinding', 'Ir\\From\\QueryClauses', 'Ir\\Mutation\\InsertConflictHandler', 'Ir\\Mutation\\InsertTargetLowering', 'Ir\\Mutation\\ReturningLowering',
            'Statement\\Mutation\\InsertDefaults', 'Statement\\Mutation\\Assignment', 'Statement\\Mutation\\ConflictResolution',
            'Ir\\Predicate\\Pattern\\PatternTest', 'Ir\\Predicate\\Pattern\\PatternKind', 'Statement\\Expression\\Operator\\PatternOperator',
            'Statement\\Expression\\Operator\\PatternMatch as ParsedPattern'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        foreach (['Ir\\Parameter', 'Ir\\Collation', 'Ir\\Expression\\BinaryOperatorLowering', 'Ir\\ArithmeticOperator', 'Statement\\Expression\\Operator\\BinaryOperator', 'Ir\\Predicate\\ComparisonOperator', 'Statement\\Expression\\Operator\\NullTestForm', 'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\BetweenTest', 'Ir\\Predicate\\MembershipTest', 'Ir\\Predicate\\MembershipItem', 'Ir\\Predicate\\FoldedMembership', 'Ir\\Predicate\\FoldedMembershipLowering', 'Model\\Rendering\\Output', 'Model\\Rendering\\PieceKind', 'Rendering\\Codec', 'Statement\\Expression\\TruthWord',
            'Statement\\Expression\\FunctionCall', 'Statement\\Expression\\Operator\\Binary', 'Ir\\Expression\\CaseExpression', 'Ir\\Expression\\CaseBranch', 'Ir\\Expression\\CastExpression', 'Ir\\Expression\\CollatedExpression', 'Ir\\Expression\\Concatenation', 'Statement\\Expression\\Collate as ParsedCollate', 'Ir\\Expression\\CastTarget', 'Ir\\Expression\\UnaryExpression', 'Ir\\Expression\\UnaryKind',
            'Ir\\Literal\\NumericLiteralLowering', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
            'Statement\\Expression\\Literal\\IntegerLiteral as ParsedInteger', 'Statement\\Expression\\Literal\\HexLiteral as ParsedHex',
            'Statement\\Expression\\Literal\\RealLiteral as ParsedReal', 'Statement\\Type\\Affinity', 'Ir\\Literal\\BlobLiteral',
            'Ir\\Literal\\HexIntegerLiteral', 'Ir\\Literal\\RealLiteral', 'Ir\\Literal\\TextLiteral', 'Ir\\Predicate\\Comparison', 'Ir\\Predicate\\Logical', 'Ir\\Predicate\\ExpressionScope', 'Ir\\Schema\\TableColumn',
            'Ir\\Schema\\DeclaredDatatypeName', 'Ir\\Schema\\NotNullConflict', 'Ir\\Schema\\DefaultLowering', 'Ir\\Mutation\\ReturnedColumn', 'Ir\\Mutation\\DefaultRow',
            'Ir\\Mutation\\UpdateConflict', 'Statement\\Expression\\ColumnUse', 'Statement\\Expression\\DoubleQuotedWord', 'Statement\\Query\\TableStar', 'Ir\\From\\SourceDeclarations', 'Statement\\Schema\\Column\\NotNull',
            'Statement\\Schema\\Column\\DefaultLiteral', 'Statement\\Schema\\Column\\DefaultExpression', 'Statement\\Schema\\Column\\DefaultWord', 'Statement\\Schema\\Table\\TableOptionKind', 'Statement\\Mutation\\MutationTarget',
            'Ir\\Schema\\CreateTable', 'Ir\\Schema\\Key\\KeyConflict', 'Ir\\Schema\\Key\\KeyTerm', 'Ir\\Schema\\Key\\PrimaryKey',
            'Ir\\Schema\\Key\\KeyTermLowering', 'Ir\\Schema\\Key\\PrimaryKeySql', 'Ir\\Schema\\Key\\PrimaryKeyLowering',
            'Statement\\Schema\\LiteralColumn', 'Statement\\Query\\Ordering\\SortDirection',
            'Statement\\Schema\\Column\\ColumnPrimaryKey', 'Statement\\Schema\\Table\\TablePrimaryKey'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        $imports .= 'use ' . $platform . '\\Model\\Statement\\Identifier\\Comparison as NameComparison;' . "\n";
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\SelectBounds;' . "\n";
        if ($dialect === 'postgres') {
            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
            foreach (['Ir\\Expression\\IntegerCase', 'Ir\\Expression\\IntegerCaseArm', 'Ir\\Expression\\IntegerCaseLowering',
                'Ir\\Predicate\\IntegerRange', 'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\IntegerAtomLowering',
                'Ir\\Predicate\\IntegerDistinctness', 'Ir\\Predicate\\IntegerDistinctnessLowering',
                'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\TruthOperator', 'Ir\\Predicate\\TruthTestLowering',
                'Statement\\Expression\\Predicate\\BooleanTest as ParsedTruthTest',
                'Statement\\Expression\\BinaryOperation', 'Statement\\Expression\\ColumnReference as ParsedColumn',
                'Statement\\Expression\\Operator\\Between as ParsedRange', 'Statement\\Expression\\Operator\\DistinctTest as ParsedDistinctness',
                'Statement\\Expression\\Operator\\UnaryOperation',
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
        if ($dialect === 'mysql') {
            foreach (['Ir\\ArithmeticOperator', 'Ir\\Literal\\IntegerTokenLowering', 'Ir\\Predicate\\IntegerRange',
                'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\RangeIntegerLiteral',
                'Statement\\Expression\\Operator\\Arithmetic as ParsedArithmetic', 'Statement\\Expression\\Predicate\\Between as ParsedRange',
                'Statement\\Literal\\BooleanLiteral as ParsedBoolean', 'Statement\\Literal\\NullLiteral as ParsedNull',
                'Statement\\Name\\ColumnUse', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
                'Statement\\Literal\\NumberForm', 'Statement\\Literal\\NumberLiteral','Statement\\Literal\\SignedLiteral',
                'Statement\\Expression\\Comparison as ParsedComparison', 'Statement\\Expression\\Logical as ParsedLogical',
                'Statement\\Expression\\Not as ParsedNot', 'Statement\\Expression\\NullTest as ParsedNullTest'] as $name) {
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
