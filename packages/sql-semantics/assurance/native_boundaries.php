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
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v1.json', 'df1e85f4672df520cfbaed5e39260a25324c8c95cc02a38c94e1fc8baa847a1d'],
            ['sqlite-insert-slots-boundaries-v1.json', 'cde9f0df0d6f1ca8ff20ddac140fd96577cf456c2facfd00e809f5d89a717d9e'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v9.json', 'be2a2c5741d020218260449ef4d3bbaba4fceca694d5941fd264fbabc2f4b8f8'],
            ['sqlite-default-boundaries-v1.json', 'c2f126dd959b203343c615191d06ddc04a97d085534da70b0137ab57f819600a'],
            ['sqlite-mutation-returning-boundaries-v1.json', '551ad79f5e71906743b7e256a7f492034d5f27e4f2fee852a5241138410ea07f'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-int4-boundaries-v1.json', '8cc5d1b6d573531fad943fac9b9dfc58181530bcbbff4ef9c66dc57733a39815'],
            ] : []),
            ['native-boundaries-v13.json', '07a00e74fa719f98db2c26bb83b399336a62674ecf4604e9d3bfb96eee9099d3'],
            ['native-insert-query-boundaries-v2.json', '527e7d1284a409e72b022a14f1280ee2d7ed1cc738b47b5d61df3a72e9cc8ee2'],
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
    foreach (['Model\\Diagnostic\\Check', 'Model\\Diagnostic\\InvalidConstruction', 'Ir\\Identifier', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Rows\\LocalSource', 'Ir\\Rows\\ValuesQuery', 'Ir\\Mutation\\InsertConflict', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\SelectBounds', 'Ir\\Ordering\\Order', 'Ir\\Ordering\\ResultSql', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate',  'Statement\\Expression\\Grouped', 'Statement\\Query\\SelectExpression', 'Ir\\ExpressionLowering',
        'Ir\\Binding\\ParameterLowering', 'Ir\\Ordering\\ProjectionLabel'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
    if ($dialect === 'sqlite') {
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
        if ($dialect === 'postgres') {
            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
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
