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
            ['sqlite-rename-boundaries-v1.json', '31296dc3a1320f0e40dd162e066cc4df0c90a47f2e7cc21b2d0fad4a5b40da5f'],
            ['sqlite-conflict-boundaries-v2.json', '8d859e50ede03278918a73739153f01cb63d1d03e139cee95587e577bc63d168'],
            ['sqlite-query-scope-boundaries-v1.json', '8deaf1505b4e3dac0dcf73c7b4149862b9145530824cb89b08933b3599c68906'],
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v1.json', 'df1e85f4672df520cfbaed5e39260a25324c8c95cc02a38c94e1fc8baa847a1d'],
            ['sqlite-insert-slots-boundaries-v3.json', 'ff1868cce0bf4174b3bbadde618f58a9383fa73c70c37ab300d83962aac00af4'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v14.json', 'bd0f8ba761ef84f4a2a81952c3d2714dc0c3bbad33f16cf9902e179f1e5bdd29'],
            ['sqlite-default-boundaries-v10.json', 'c8f8df9a7f46f80a597729373e56e103d17fb025edc4213be5c4a51fd932725a'],
            ['sqlite-mutation-returning-boundaries-v2.json', 'bc18d0046658c381649211e67f76dbd4faaba16e37526e35eca14e1e6a9fd956'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-mutation-returning-boundaries-v2.json', '082d7dd3af450a824ec217de545754e2f034eb33027dbb342456531b31dcefc3'],
            ] : []),
            ['native-boundaries-v27.json', 'c420636c9ab1742e0643e5462f1fe4af60fcd1c2c86f3f95498306f46acb74e4'],
            ['native-insert-query-boundaries-v4.json', '67796e3b509a778308dcdf70c04ebcf7d455e9014faac77c426f6ee105290373'],
        ],
    };
    $records[] = ['identifier-boundaries-v1.json', 'e526dfe9c9f107bf37e8aa1a38a006e7615f9dcf912d2087d564c19b500c7ab0'];
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
            'Ir\\Schema\\Alter\\RenameColumn', 'Ir\\Schema\\Alter\\RenameTable',
            'Model\\Statement\\Declaration\\RelationKind', 'Model\\Statement\\Reference\\Table\\DeclaredTable',
            'Statement\\Schema\\AlterRenameColumn', 'Statement\\Schema\\AlterRenameTable',
            'Ir\\Schema\\CreateTable', 'Ir\\Schema\\Key\\KeyConflict', 'Ir\\Schema\\Key\\KeyTerm', 'Ir\\Schema\\Key\\PrimaryKey',
            'Ir\\Schema\\Key\\KeyTermLowering', 'Ir\\Schema\\Key\\TableKeySql', 'Ir\\Schema\\Key\\TableKeyLowering',
            'Ir\\Schema\\Key\\TableKeyLayout', 'Ir\\Schema\\Key\\UniqueKey',
            'Statement\\Schema\\LiteralColumn', 'Statement\\Query\\Ordering\\SortDirection',
            'Statement\\Schema\\Column\\ColumnPrimaryKey', 'Statement\\Schema\\Table\\TablePrimaryKey',
            'Statement\\Schema\\Column\\ColumnUnique', 'Statement\\Schema\\Table\\TableUnique',
            'Statement\\Schema\\Column\\Collation as ParsedCollation',
            'Ir\\Schema\\Predicate\\CheckConstraint', 'Ir\\Schema\\Predicate\\CheckLowering', 'Ir\\Schema\\Predicate\\ImplicitCheckLabel', 'Ir\\Schema\\Predicate\\ComparisonSpelling', 'Ir\\Schema\\Predicate\\ImplicitCheckLowering', 'Ir\\Schema\\Predicate\\DefinitionSource', 'Ir\\Schema\\TableDeclarationSql', 'Statement\\Schema\\Column\\ColumnCheck', 'Statement\\Schema\\Column\\ConstraintName', 'Statement\\Schema\\Table\\TableCheck',
            'Ir\\Schema\\Generation\\GeneratedGraph', 'Ir\\Schema\\Generation\\GeneratedLowering', 'Ir\\Schema\\Generation\\ColumnGeneration', 'Ir\\Schema\\Generation\\Storage',
            'Ir\\Schema\\DeclaredDatatypeLowering', 'Statement\\Schema\\Column\\Generated', 'Statement\\Schema\\Column\\GeneratedStorage', 'Statement\\Type\\ColumnDomain',
            'Ir\\Schema\\CreateSchema', 'Ir\\Schema\\Query\\CreateTableAs', 'Ir\\Schema\\Query\\CreateAsAffinity',
            'Ir\\Schema\\Query\\CreateAsLayout', 'Ir\\Schema\\Query\\CreatedColumn', 'Ir\\Schema\\Query\\CreatedColumnDeclaration',
            'Ir\\Rows\\ValuesLowering', 'Statement\\Query\\ValuesClause'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\SelectBounds;' . "\n";
        if ($dialect === 'postgres') {
            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
            foreach (['Ir\\Expression\\IntegerCase', 'Ir\\Expression\\IntegerCaseArm', 'Ir\\Expression\\IntegerCaseLowering',
                'Ir\\Expression\\BuiltinCast', 'Ir\\Expression\\BuiltinCastLowering', 'Ir\\Expression\\CastDestination', 'Ir\\Expression\\WideIntegerLiteral', 'Ir\\Binding\\ParameterSignature', 'Ir\\From\\JoinBinding',
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
                'Model\\Statement\\Type\\Known', 'Statement\\Type\\Descriptor\\Builtin', 'Ir\\Rows\\Compound', 'Ir\\Rows\\CompoundOperator', 'Ir\\Ordering\\ResultLowering', 'Statement\\Literal\\PositionalParameter', 'Statement\\Query\\SetOperation', 'Statement\\Query\\SetQuantifier', 'Ir\\Rows\\ResultKind'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
        if ($dialect === 'mysql') {
            foreach (['Ir\\ArithmeticOperator', 'Ir\\Literal\\IntegerTokenLowering', 'Ir\\Predicate\\IntegerRange',
                'Ir\\Expression\\SignedCast', 'Ir\\Expression\\UnsignedCast', 'Ir\\Expression\\IntegralCastLowering',
                'Statement\\Literal\\Parameter as ParsedParameter',
                'Statement\\Expression\\Conversion\\Cast', 'Statement\\Type\\Kind\\CastKind',
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
    $imports .= 'use ' . $platform . '\\Model\\Statement\\Identifier\\Comparison as NameComparison;' . "\n";
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
