<?php

declare(strict_types=1);

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require $argv[1];
require __DIR__ . '/value_storage.php';
require __DIR__ . '/scalar_dispatch.php';
require __DIR__ . '/native_boundaries.php';
$root = $argv[2];
$overlay = $argv[3] ?? '';
$dialect = $argv[4] ?? 'sqlite';
$platform = 'SqlSemantics\\Platform\\' . match ($dialect) {
    'mysql' => 'MySql',
    'postgres' => 'PostgreSql',
    'sqlite' => 'Sqlite',
};
$prefix = $platform . '\\Ir\\';
$operands = ['Arithmetic', 'ColumnReference', 'IntegerLiteral', 'NullLiteral', 'Parameter'];
if ($dialect === 'sqlite') {
    array_push($operands, 'Expression\\CaseExpression', 'Expression\\CastExpression', 'Expression\\CollatedExpression', 'Expression\\Nulls\\Coalesce', 'Expression\\Concatenation', 'Expression\\UnaryExpression', 'Literal\\BlobLiteral', 'Literal\\HexIntegerLiteral', 'Predicate\\BetweenTest', 'Predicate\\Comparison', 'Predicate\\FoldedMembership', 'Predicate\\Logical', 'Predicate\\MembershipTest', 'Predicate\\Pattern\\PatternTest', 'Predicate\\TruthTest', 'Literal\\RealLiteral', 'Literal\\TextLiteral');
    sort($operands);
}
$properties = [
    'Arithmetic' => ['operator' => ['ArithmeticOperator'], 'left' => $operands, 'right' => $operands],
    'ArithmeticOperator' => [],
    'ColumnReference' => ['slot' => ['int']],
    'Identifier' => ['value' => ['string']],
    'IntegerLiteral' => ['decimal' => ['string']],
    'NullLiteral' => [],
    'Parameter' => ['slot' => ['int'], 'label' => ['string']],
    'ResultColumn' => ['expression' => $operands, 'name' => ['Identifier']],
    'Select' => ['columns' => ['array'], 'source' => ['?TableSource']],
    'TableSource' => ['columns' => ['array'], 'schema' => ['Identifier'], 'table' => ['Identifier']],
];
$methods = [
    'Arithmetic' => ['__construct'], 'ArithmeticOperator' => [],
    'ColumnReference' => ['__construct'], 'Identifier' => ['__construct', 'quoted', 'key'],
    'IntegerLiteral' => ['__construct'], 'NullLiteral' => [], 'Parameter' => ['__construct'],
    'ResultColumn' => ['__construct'], 'Select' => ['__construct', 'profileId', 'toString'],
    'TableSource' => ['__construct'],
];
if ($dialect !== 'sqlite') {
    unset($properties['Parameter'], $methods['Parameter']);
    $properties['Binding\\Parameter'] = ['position' => ['int']];
    $methods['Binding\\Parameter'] = ['__construct', 'toString'];
    $properties['BooleanLiteral'] = ['value' => ['bool']];
    $methods['BooleanLiteral'] = ['__construct'];
    $properties['Arithmetic']['left'] = $properties['Arithmetic']['right'] = ['Arithmetic', 'ColumnReference', 'IntegerLiteral'];
    $properties['ResultColumn']['expression'] = ['Arithmetic', 'Binding\\Parameter', 'BooleanLiteral', 'ColumnReference', 'IntegerLiteral', 'NullLiteral'];
    $properties['Select']['release'] = [$platform . '\\Model\\Contract\\GrammarRelease'];
}
if ($dialect === 'postgres') {
    $builtinCast = 'Expression\\BuiltinCast';
    $castDestination = 'Expression\\CastDestination';
    $properties[$builtinCast] = [
        'target' => [$castDestination],
        'operand' => ['Binding\\Parameter', 'BooleanLiteral', 'ColumnReference', $builtinCast, 'Expression\\TextLiteral', 'Expression\\WideIntegerLiteral', 'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference', 'IntegerLiteral', 'NullLiteral'],
    ];
    $properties[$castDestination] = $methods[$castDestination] = [];
    $properties['Binding\\Parameter']['input'] = [$castDestination];
    $methods[$builtinCast] = ['__construct'];
    $properties['Expression\\WideIntegerLiteral'] = ['decimal' => ['string']];
    $methods['Expression\\WideIntegerLiteral'] = ['__construct'];
    $properties['Expression\\WideIntegerNull'] = $methods['Expression\\WideIntegerNull'] = [];
    $properties['Expression\\TextLiteral'] = ['value' => ['string']];
    $methods['Expression\\TextLiteral'] = ['__construct', 'toString'];
    $properties['Expression\\CollatedText'] = ['collation' => ['Expression\\TextCollation'],
        'operand' => ['Expression\\TextLiteral', 'From\\TextColumnReference', 'NullLiteral']];
    $methods['Expression\\CollatedText'] = ['__construct'];
    $properties['Expression\\TextCollation'] = $methods['Expression\\TextCollation'] = [];
    $textComparison = 'Predicate\\Text\\TextComparison';
    $textOperands = ['Expression\\TextLiteral', 'From\\TextColumnReference', 'NullLiteral'];
    $properties[$textComparison] = ['operator' => ['Predicate\\Text\\TextOperator'], 'left' => $textOperands,
        'right' => $textOperands, 'collation' => ['Expression\\TextCollation']];
    $methods[$textComparison] = ['__construct'];
    $properties['Predicate\\Text\\TextOperator'] = $methods['Predicate\\Text\\TextOperator'] = [];
    $properties['From\\TextColumnReference'] = ['slot' => ['int'], 'occurrence' => ['int']];
    $methods['From\\TextColumnReference'] = ['__construct'];
    $properties['From\\BooleanColumnReference'] = ['slot' => ['int'], 'occurrence' => ['int']];
    $methods['From\\BooleanColumnReference'] = ['__construct'];
    $properties['From\\WideIntegerColumnReference'] = ['slot' => ['int'], 'occurrence' => ['int']];
    $methods['From\\WideIntegerColumnReference'] = ['__construct'];
    $properties['From\\ColumnKind'] = $methods['From\\ColumnKind'] = [];
    $properties['TableSource']['columnKinds'] = ['array'];
    $properties['TableSource']['kind'] = ['From\\ExternalRelationKind'];
    $properties['From\\ExternalRelationKind'] = $methods['From\\ExternalRelationKind'] = [];
    $methods['TableSource'][] = 'columnKind';
    array_push($properties['ResultColumn']['expression'], $builtinCast, 'Expression\\WideIntegerLiteral', 'Expression\\TextLiteral',
        'Expression\\CollatedText', 'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference', $textComparison);
    $integerCase = 'Expression\\IntegerCase';
    $caseArm = 'Expression\\IntegerCaseArm';
    $caseValues = ['Arithmetic', 'ColumnReference', $integerCase, 'IntegerLiteral', 'NullLiteral'];
    $properties[$integerCase] = ['arms' => ['array'], 'otherwise' => [...$caseValues, 'null']];
    $properties[$caseArm] = ['condition' => ['BooleanLiteral', 'NullLiteral', 'Predicate\\IntegerComparison', 'Predicate\\LogicalPredicate', 'Predicate\\NullTest'],
        'result' => $caseValues];
    $range = 'Predicate\\IntegerRange';
    $rangeLeaves = ['ColumnReference', 'Expression\\WideIntegerLiteral', 'Expression\\WideIntegerNull', 'From\\WideIntegerColumnReference', 'IntegerLiteral', 'NullLiteral'];
    $properties[$range] = [
        'subject' => $rangeLeaves, 'lower' => $rangeLeaves, 'upper' => $rangeLeaves,
        'negated' => ['bool'], 'symmetric' => ['bool'],
    ];
    $truthTest = 'Predicate\\TruthTest';
    $truthOperator = 'Predicate\\TruthOperator';
    $properties[$truthOperator] = $methods[$truthOperator] = [];
    $methods[$truthTest] = ['__construct'];
    $distinctness = 'Predicate\\IntegerDistinctness';
    $properties[$distinctness] = ['left' => $rangeLeaves, 'right' => $rangeLeaves, 'negated' => ['bool']];
    $methods[$integerCase] = $methods[$caseArm] = $methods[$range] = $methods[$distinctness] = ['__construct'];
    array_push($properties[$caseArm]['condition'], $range, $distinctness, $truthTest, $textComparison, 'From\\BooleanColumnReference');
    sort($properties[$caseArm]['condition']);
    array_push($properties['ResultColumn']['expression'], $range, $distinctness, $truthTest, 'Predicate\\NullTest', 'Predicate\\IntegerComparison');
    $properties['Arithmetic']['left'][] = $integerCase;
    $properties['Arithmetic']['right'][] = $integerCase;
    $properties['ResultColumn']['expression'][] = $integerCase;
    sort($properties['Arithmetic']['left']);
    sort($properties['Arithmetic']['right']);
    sort($properties['ResultColumn']['expression']);
    $methods['Identifier'] = ['__construct', 'quoted'];
    $properties['TableSource']['only'] = ['bool'];
}
if ($dialect === 'mysql') {
    $properties['From\\TextColumnReference'] = ['slot' => ['int'], 'occurrence' => ['int']];
    $methods['From\\TextColumnReference'] = ['__construct'];
    $properties['From\\Varchar'] = ['length' => ['int'], 'collation' => ['From\\TextCollation'], 'nullable' => ['bool']];
    $methods['From\\Varchar'] = ['__construct'];
    $properties['From\\ColumnKind'] = [];
    $methods['From\\ColumnKind'] = ['nullable'];
    $properties['From\\TextCollation'] = $methods['From\\TextCollation'] = [];
    $properties['TableSource']['columnTypes'] = ['array'];
    $properties['TableSource']['kind'] = ['From\\ExternalRelationKind'];
    $properties['From\\ExternalRelationKind'] = $methods['From\\ExternalRelationKind'] = [];
    $textOperator = 'Predicate\\Text\\TextOperator';
    $textComparison = 'Predicate\\Text\\TextComparison';
    $collatedText = 'Expression\\CollatedText';
    $properties[$collatedText] = ['operand' => ['From\\TextColumnReference', 'Literal\\TextLiteral'], 'collation' => ['From\\TextCollation']];
    $methods[$collatedText] = ['__construct'];
    $textOperands = [$collatedText, 'From\\TextColumnReference', 'Literal\\TextLiteral', 'NullLiteral'];
    $properties[$textOperator] = $methods[$textOperator] = [];
    $properties[$textComparison] = ['operator' => [$textOperator], 'left' => $textOperands,
        'right' => $textOperands, 'collation' => ['From\\TextCollation']];
    $methods[$textComparison] = ['__construct'];
    array_push($properties['ResultColumn']['expression'], 'From\\TextColumnReference', 'Predicate\\NullTest', $textComparison, $collatedText);
    $properties['IntegerLiteral']['leadingZeroes'] = ['int'];
    $properties['IntegerLiteral']['negativeZero'] = ['bool'];
    $methods['IntegerLiteral'][] = 'toString';
    $range = 'Predicate\\IntegerRange';
    $rangeLiteral = 'Predicate\\RangeIntegerLiteral';
    $rangeLeaves = ['ColumnReference', 'NullLiteral', $rangeLiteral];
    $properties[$range] = ['subject' => $rangeLeaves, 'lower' => $rangeLeaves, 'upper' => $rangeLeaves, 'negated' => ['bool']];
    $properties[$rangeLiteral] = $properties['IntegerLiteral'];
    $methods[$range] = ['__construct'];
    $methods[$rangeLiteral] = ['__construct', 'toString'];
    $properties['ResultColumn']['expression'][] = $range;
    sort($properties['ResultColumn']['expression']);
}
$selectBounds = $dialect === 'sqlite' ? 'From\\SelectBounds' : 'SelectBounds';
$internal = ['ExpressionLowering', $selectBounds, 'SelectLowering', 'SelectSql', 'SourceBinding'];
if ($dialect === 'postgres') {
    $internal[] = 'Expression\\IntegerCaseLowering';
    $internal[] = 'Expression\\BuiltinCastLowering';
    $internal[] = 'Expression\\CollatedTextLowering';
    $internal[] = 'Predicate\\Text\\TextComparisonLowering';
    $internal[] = 'Predicate\\IntegerRangeLowering';
    $internal[] = 'Predicate\\IntegerAtomLowering';
    $internal[] = 'Predicate\\IntegerDistinctnessLowering';
    $internal[] = 'Predicate\\TruthTestLowering';
}
if ($dialect === 'mysql') {
    array_push($internal, 'Literal\\IntegerTokenLowering', 'Predicate\\IntegerRangeLowering', 'From\\VarcharLowering', 'Predicate\\Text\\TextComparisonLowering', 'Expression\\CollatedTextLowering');
}
$pureCollaborators = [$selectBounds, 'SelectSql'];
if ($dialect === 'postgres') {
    $internal[] = 'Binding\\ParameterSignature';
    $pureCollaborators[] = 'Binding\\ParameterSignature';
}
$roots = ['Select'];
$enumCases = ['ArithmeticOperator' => ['Add' => '+', 'Subtract' => '-', 'Multiply' => '*']];
if ($dialect === 'mysql') {
    $signedCast = 'Expression\\SignedCast';
    $properties[$signedCast] = ['operand' => ['Arithmetic', 'BooleanLiteral', 'ColumnReference', $signedCast, 'IntegerLiteral', 'NullLiteral']];
    $methods[$signedCast] = ['__construct'];
    $properties['ResultColumn']['expression'][] = $signedCast;
    sort($properties['ResultColumn']['expression']);
    $unsignedCast = 'Expression\\UnsignedCast';
    array_push($properties[$signedCast]['operand'], $unsignedCast, 'Predicate\\RangeIntegerLiteral');
    sort($properties[$signedCast]['operand']);
    $properties[$unsignedCast] = $properties[$signedCast];
    $properties['Arithmetic']['left'][] = 'Predicate\\RangeIntegerLiteral';
    $properties['Arithmetic']['right'][] = 'Predicate\\RangeIntegerLiteral';
    $methods[$unsignedCast] = ['__construct'];
    $properties['ResultColumn']['expression'][] = $unsignedCast;
    sort($properties['ResultColumn']['expression']);
    $internal[] = 'Expression\\IntegralCastLowering';
    $enumCases['ArithmeticOperator'] += ['IntegerDivide' => 'DIV', 'Remainder' => '%'];
    $enumCases['From\\ColumnKind'] = ['SignedInt' => 'int', 'RequiredSignedInt' => 'int-not-null'];
    $enumCases['From\\ExternalRelationKind'] = ['BaseTable' => 'table', 'TemporaryTable' => 'temporary-table', 'View' => 'view'];
    $methods['From\\ExternalRelationKind'] = ['isTable'];
    $enumCases['From\\TextCollation'] = ['Utf8mb4Bin' => 'utf8mb4_bin'];
    $enumCases['Predicate\\Text\\TextOperator'] = ['Equal' => '=', 'NullSafeEqual' => '<=>', 'NotEqual' => '<>',
        'Less' => '<', 'LessOrEqual' => '<=', 'Greater' => '>', 'GreaterOrEqual' => '>='];
}
if ($dialect === 'postgres') {
    $enumCases['From\\ColumnKind'] = ['Int4' => 'int4', 'Text' => 'text', 'Boolean' => 'bool', 'Int8' => 'int8'];
    $enumCases['From\\ExternalRelationKind'] = ['BaseTable' => 'table', 'View' => 'view', 'MaterializedView' => 'materialized-view'];
    $enumCases['Expression\\CastDestination'] = ['Integer' => 'int4', 'WideInteger' => 'int8', 'Boolean' => 'bool', 'Text' => 'text'];
    $enumCases['Expression\\TextCollation'] = ['C' => 'C'];
    $enumCases['Predicate\\Text\\TextOperator'] = ['Equal' => '=', 'NotEqual' => '<>', 'Less' => '<', 'LessOrEqual' => '<=', 'Greater' => '>', 'GreaterOrEqual' => '>='];
    $enumCases['ArithmeticOperator'] += ['Divide' => '/', 'Remainder' => '%', 'BitAnd' => '&', 'BitOr' => '|', 'BitXor' => '#'];
}
if ($dialect === 'sqlite') {
    $enumCases['ArithmeticOperator'] += [
        'Divide' => '/', 'Remainder' => '%', 'BitAnd' => '&', 'BitOr' => '|', 'ShiftLeft' => '<<', 'ShiftRight' => '>>',
    ];
    $properties['Expression\\UnaryExpression'] = ['kind' => ['Expression\\UnaryKind'], 'operand' => $operands];
    $methods['Expression\\UnaryExpression'] = ['__construct'];
    $properties['Expression\\UnaryKind'] = $methods['Expression\\UnaryKind'] = [];
    $enumCases['Expression\\UnaryKind'] = ['Plus' => '+', 'Minus' => '-', 'Not' => 'NOT', 'Complement' => '~'];
    $properties['Predicate\\FoldedMembership'] = ['matches' => ['bool'], 'bindings' => ['array']];
    $methods['Predicate\\FoldedMembership'] = ['__construct'];
    $internal[] = 'Predicate\\FoldedMembershipLowering';
    $properties['Predicate\\Pattern\\PatternKind'] = $methods['Predicate\\Pattern\\PatternKind'] = [];
    $enumCases['Predicate\\Pattern\\PatternKind'] = ['AsciiLike' => 'LIKE', 'Glob' => 'GLOB'];
    $properties['Predicate\\Pattern\\PatternTest'] = ['kind' => ['Predicate\\Pattern\\PatternKind'], 'subject' => $operands,
        'pattern' => $operands, 'escape' => [...$operands, 'null'], 'negated' => ['bool']];
    $methods['Predicate\\Pattern\\PatternTest'] = ['__construct'];
    $properties['Predicate\\MembershipItem'] = ['expression' => $operands];
    $properties['Predicate\\MembershipTest'] = ['operand' => $operands, 'items' => ['array'], 'negated' => ['bool']];
    $methods['Predicate\\MembershipItem'] = $methods['Predicate\\MembershipTest'] = ['__construct'];
    $properties['Predicate\\BetweenTest'] = ['operand' => $operands, 'lower' => $operands, 'upper' => $operands, 'negated' => ['bool']];
    $methods['Predicate\\BetweenTest'] = ['__construct'];
    $properties['Predicate\\TruthTest'] = ['operand' => $operands, 'desired' => ['bool'], 'negated' => ['bool']];
    $methods['Predicate\\TruthTest'] = ['__construct'];
    $roots[] = 'Aggregation\\GroupedSelect';
    $properties['Aggregation\\GroupedSelect'] = [
        'source' => ['?TableSource'], 'keys' => ['array'], 'columns' => ['array'], 'where' => [...$operands, 'null'],
        'having' => ['?Aggregation\\CountComparison'],
    ];
    $methods['Aggregation\\GroupedSelect'] = $methods['Select'];
    $properties['Collation'] = $methods['Collation'] = [];
    $enumCases['Collation'] = ['Binary' => 'BINARY', 'NoCase' => 'NOCASE', 'RightTrim' => 'RTRIM'];
    $properties['Aggregation\\Count'] = ['argument' => ['?ColumnReference'], 'distinctCollation' => ['?Collation']];
    $properties['Aggregation\\CountComparison'] = [
        'count' => ['Aggregation\\Count'], 'operator' => ['Predicate\\ComparisonOperator'], 'threshold' => ['IntegerLiteral', 'NullLiteral', 'Parameter'], 'thresholdFirst' => ['bool'],
    ];
    $properties['Aggregation\\GroupKey'] = ['column' => ['ColumnReference'], 'collation' => ['Collation']];
    $properties['Aggregation\\GroupColumn'] = ['value' => ['Aggregation\\Count', 'ColumnReference'], 'name' => ['Identifier']];
    $methods['Aggregation\\Count'] = $methods['Aggregation\\GroupKey'] = $methods['Aggregation\\GroupColumn'] = ['__construct'];
    $methods['Aggregation\\CountComparison'] = ['__construct'];
    array_push($internal, 'Aggregation\\CountBinding', 'Aggregation\\GroupBinding', 'Aggregation\\GroupedLowering', 'Aggregation\\GroupedSql', 'Aggregation\\GroupFilterLowering');
    $pureCollaborators[] = 'Aggregation\\GroupedSql';
    $predicates = $operands;
    $nullableCaseOperands = [...$operands, 'null'];
    $properties['Expression\\CaseExpression'] = ['base' => $nullableCaseOperands, 'branches' => ['array'], 'otherwise' => $nullableCaseOperands];
    $properties['Expression\\CaseBranch'] = ['when' => $operands, 'then' => $operands];
    $methods['Expression\\CaseExpression'] = $methods['Expression\\CaseBranch'] = ['__construct'];
    $properties['Expression\\Concatenation'] = ['left' => $operands, 'right' => $operands];
    $methods['Expression\\Concatenation'] = ['__construct'];
    $properties['Expression\\CollatedExpression'] = ['operand' => $operands, 'collation' => ['Collation']];
    $methods['Expression\\CollatedExpression'] = ['__construct'];
    $internal[] = 'Rows\\ValueRowOperand';
    $pureCollaborators[] = 'Rows\\ValueRowOperand';
    $properties['Expression\\Nulls\\Coalesce'] = ['arguments' => ['array'], 'ifNull' => ['bool']];
    $properties['Expression\\Nulls\\CoalesceArgument'] = ['expression' => $operands];
    $methods['Expression\\Nulls\\Coalesce'] = $methods['Expression\\Nulls\\CoalesceArgument'] = ['__construct'];
    $internal[] = 'Expression\\Nulls\\CoalesceLowering';
    $properties['Expression\\CastExpression'] = ['target' => ['Expression\\CastTarget'], 'operand' => $operands, 'typeName' => ['?Expression\\CastTypeName']];
    $properties['Expression\\CastTypeName'] = ['value' => ['string']];
    $methods['Expression\\CastTypeName'] = ['__construct', 'target', 'quoted', 'payloadBytes'];
    $methods['Expression\\CastExpression'] = ['__construct'];
    $properties['Expression\\CastTarget'] = $methods['Expression\\CastTarget'] = [];
    $enumCases['Expression\\CastTarget'] = ['Integer' => 'INTEGER', 'Real' => 'REAL', 'Numeric' => 'NUMERIC', 'Text' => 'TEXT', 'Blob' => 'BLOB'];
    $properties['Literal\\BlobLiteral'] = ['hex' => ['string']];
    $properties['Literal\\HexIntegerLiteral'] = ['digits' => ['string'], 'negated' => ['bool']];
    $properties['Literal\\RealLiteral'] = ['whole' => ['string'], 'fraction' => ['?string'], 'exponent' => ['?string'], 'negative' => ['bool'], 'uppercaseExponent' => ['bool']];
    $properties['Literal\\TextLiteral'] = ['value' => ['string']];
    foreach (['Literal\\BlobLiteral', 'Literal\\HexIntegerLiteral', 'Literal\\RealLiteral', 'Literal\\TextLiteral'] as $literal) {
        $methods[$literal] = ['__construct'];
    }
    $nullablePredicates = [...$predicates, 'null'];
    $properties['ColumnReference']['source'] = ['int'];
    $properties['Select']['joins'] = ['array'];
    $properties['Select']['orderBy'] = ['array'];
    $properties['Select']['where'] = $nullablePredicates;
    $properties['Select']['distinct'] = ['bool'];
    $properties['Select']['limit'] = ['?Ordering\\Limit'];
    array_push($methods['Literal\\RealLiteral'], 'payloadBytes', 'toString');
    $methods['Literal\\HexIntegerLiteral'][] = 'toString';
    $methods['Literal\\TextLiteral'] = ['__construct', 'toString', 'payloadBytes'];
    $properties['TableSource']['kind'] = ['From\\ExternalRelationKind'];
    $properties['From\\ExternalRelationKind'] = $methods['From\\ExternalRelationKind'] = [];
    $enumCases['From\\ExternalRelationKind'] = ['BaseTable' => 'table', 'View' => 'view'];
    $properties['Select']['source'] = ['Rows\\LocalSource', 'TableSource', 'null'];
    $properties['From\\Join'] = ['kind' => ['From\\JoinKind'], 'on' => $nullablePredicates, 'source' => ['Rows\\LocalSource', 'TableSource']];
    $properties['Ordering\\Order'] = ['descending' => ['bool'], 'nullsFirst' => ['bool'], 'position' => ['int']];
    $properties['Ordering\\Limit'] = ['count' => ['IntegerLiteral', 'Parameter'], 'offset' => ['IntegerLiteral', 'Parameter', 'null'], 'offsetFirst' => ['bool']];
    $properties['Mutation\\Delete'] = ['target' => ['TableSource'], 'where' => $nullablePredicates, 'returning' => ['array']];
    $properties['Mutation\\ReturnedColumn'] = ['reference' => ['ColumnReference'], 'name' => ['Identifier']];
    $methods['Mutation\\ReturnedColumn'] = ['__construct'];
    array_push($internal, 'Mutation\\ReturningLowering', 'Mutation\\ReturningSql');
    $pureCollaborators[] = 'Mutation\\ReturningSql';
    $methods['Mutation\\Delete'] = $methods['Select'];
    $properties['Mutation\\Insert'] = [
        'target' => ['TableSource'], 'writes' => ['array'], 'input' => ['Mutation\\DefaultRow', 'Rows\\ValuesQuery', 'Select'],
        'conflict' => ['Mutation\\InsertConflict'], 'handler' => ['Mutation\\InsertConflictHandler'], 'returning' => ['array'],
    ];
    $methods['Mutation\\Insert'] = $methods['Select'];
    $properties['Mutation\\DefaultRow'] = $methods['Mutation\\DefaultRow'] = [];
    $internal[] = 'Mutation\\InsertTargetLowering';
    $properties['Mutation\\Update'] = [
        'target' => ['TableSource'], 'assignments' => ['array'], 'where' => $nullablePredicates, 'conflict' => ['Mutation\\UpdateConflict'], 'returning' => ['array'],
    ];
    $methods['Mutation\\Update'] = $methods['Select'];
    $properties['Mutation\\UpdateAssignment'] = ['targetSlot' => ['int'], 'value' => $operands];
    $methods['Mutation\\UpdateAssignment'] = ['__construct'];
    $properties['Rows\\ValueRow'] = ['values' => ['array']];
    $properties['Rows\\LocalSource'] = ['columns' => ['array'], 'query' => ['Rows\\RecursiveDefinition', 'Rows\\ValuesQuery']];
    $properties['Rows\\RecursiveColumn'] = ['expression' => $operands];
    $properties['Rows\\RecursiveDefinition'] = ['anchor' => ['Rows\\ValuesQuery'], 'step' => ['array'], 'unionAll' => ['bool'], 'where' => $nullablePredicates];
    $methods['Rows\\RecursiveColumn'] = ['__construct'];
    $methods['Rows\\RecursiveDefinition'] = ['__construct', 'scopedExpressions'];
    $internal[] = 'Rows\\RecursiveLowering';
    $properties['Rows\\ValuesQuery'] = ['rows' => ['array']];
    $methods['Rows\\ValueRow'] = ['__construct', 'toString'];
    $methods['Rows\\LocalSource'] = ['__construct'];
    $methods['Rows\\ValuesQuery'] = $methods['Select'];
    $properties['Schema\\DropTable'] = ['schema' => ['Identifier'], 'table' => ['Identifier'], 'ifExists' => ['bool']];
    $methods['Schema\\DropTable'] = $methods['Select'];
    $properties['Schema\\DropView'] = ['schema' => ['Identifier'], 'view' => ['Identifier'], 'ifExists' => ['bool']];
    $methods['Schema\\DropView'] = $methods['Select'];
    $properties['Schema\\CreateTable'] = [
        'schema' => ['Identifier'], 'table' => ['Identifier'], 'columns' => ['array'], 'ifNotExists' => ['bool'],
        'strict' => ['bool'], 'withoutRowid' => ['bool'], 'keys' => ['array'], 'checks' => ['array'], 'primaryKey' => ['?Schema\\Key\\PrimaryKey'],
    ];
    $properties['Schema\\Key\\KeyTerm'] = ['slot' => ['int'], 'descending' => ['bool'], 'collation' => ['Collation']];
    $properties['Schema\\Key\\PrimaryKey'] = [
        'terms' => ['array'], 'inline' => ['bool'], 'conflict' => ['?Schema\\Key\\KeyConflict'], 'autoIncrement' => ['bool'],
    ];
    $properties['Schema\\Key\\UniqueKey'] = ['terms' => ['array'], 'inline' => ['bool'], 'conflict' => ['?Schema\\Key\\KeyConflict']];
    $methods['Schema\\Key\\KeyTerm'] = $methods['Schema\\Key\\PrimaryKey'] = $methods['Schema\\Key\\UniqueKey'] = ['__construct'];
    $properties['Schema\\Key\\KeyConflict'] = $methods['Schema\\Key\\KeyConflict'] = [];
    $enumCases['Schema\\Key\\KeyConflict'] = ['Abort' => 'ABORT', 'Fail' => 'FAIL', 'Ignore' => 'IGNORE',
        'Replace' => 'REPLACE', 'Rollback' => 'ROLLBACK'];
    array_push($internal, 'Schema\\Key\\TableKeySql', 'Schema\\Key\\TableKeyLowering', 'Schema\\Key\\TableKeyLayout', 'Schema\\Key\\KeyTermLowering');
    array_push($pureCollaborators, 'Schema\\Key\\TableKeySql', 'Schema\\Key\\TableKeyLayout');
    $methods['Schema\\CreateTable'] = $methods['Select'];
    $properties['Schema\\Predicate\\CheckConstraint'] = [
        'label' => ['Identifier', 'Schema\\Predicate\\ImplicitCheckLabel'], 'condition' => array_values(array_diff($operands, ['Parameter'])), 'column' => ['?int'],
    ];
    $methods['Schema\\Predicate\\CheckConstraint'] = ['__construct', 'checkPresentation'];
    $implicitLabel = 'Schema\\Predicate\\ImplicitCheckLabel';
    $comparisonSpelling = 'Schema\\Predicate\\ComparisonSpelling';
    $properties[$implicitLabel] = ['columnName' => ['Identifier'], 'token' => [$comparisonSpelling], 'quoted' => ['bool'],
        'beforeOperator' => ['string'], 'afterOperator' => ['string'], 'leadingZeroes' => ['int']];
    $methods[$implicitLabel] = ['__construct', 'operation', 'expression'];
    $properties[$comparisonSpelling] = $methods[$comparisonSpelling] = [];
    $enumCases[$comparisonSpelling] = [
        'Equal' => '=', 'DoubleEqual' => '==', 'NotEqual' => '<>', 'BangEqual' => '!=',
        'Less' => '<', 'LessOrEqual' => '<=', 'Greater' => '>', 'GreaterOrEqual' => '>=',
    ];
    $properties['Schema\\Predicate\\DefinitionSource'] = ['columns' => ['array']];
    $methods['Schema\\Predicate\\DefinitionSource'] = ['__construct'];
    array_push($internal, 'Schema\\Predicate\\CheckLowering', 'Schema\\Predicate\\ImplicitCheckLowering', 'Schema\\TableDeclarationSql');
    array_push($pureCollaborators, 'Schema\\Predicate\\DefinitionSource', 'Schema\\TableDeclarationSql');
    $properties['Schema\\Query\\CreateTableAs'] = [
        'schema' => ['Identifier'], 'table' => ['Identifier'], 'query' => ['Rows\\ValuesQuery', 'Select'], 'ifNotExists' => ['bool'],
    ];
    $methods['Schema\\Query\\CreateTableAs'] = ['__construct', 'columns', 'profileId', 'toString'];
    $properties['Schema\\Query\\CreatedColumn'] = ['name' => ['Identifier'], 'type' => ['Schema\\Query\\CreatedColumnDeclaration']];
    $methods['Schema\\Query\\CreatedColumn'] = ['__construct'];
    $properties['Schema\\Query\\CreatedColumnDeclaration'] = $methods['Schema\\Query\\CreatedColumnDeclaration'] = [];
    $enumCases['Schema\\Query\\CreatedColumnDeclaration'] = ['Untyped' => '', 'Integer' => 'INT', 'Numeric' => 'NUM', 'Real' => 'REAL', 'Text' => 'TEXT'];
    array_push($roots, 'Schema\\Alter\\RenameTable', 'Schema\\Alter\\RenameColumn', 'Schema\\Alter\\AddColumn', 'Schema\\Alter\\AddGeneratedColumn');
    array_push($internal, 'Schema\\Alter\\RenameLowering', 'Schema\\Alter\\AddColumnLowering', 'Schema\\Alter\\AlterTargetLowering', 'Schema\\Alter\\AppendColumnBounds');
    $pureCollaborators[] = 'Schema\\Alter\\AppendColumnBounds';
    $properties['Schema\\Alter\\AddColumn'] = [
        'schema' => ['Identifier'], 'table' => ['Identifier'], 'priorHeading' => ['array'],
        'strict' => ['bool'], 'column' => ['Schema\\TableColumn'], 'checks' => ['array'],
    ];
    $properties['Schema\\Alter\\AddGeneratedColumn'] = $properties['Schema\\Alter\\AddColumn'];
    $methods['Schema\\Alter\\AddColumn'] = $methods['Schema\\Alter\\AddGeneratedColumn'] = $methods['Select'];
    $properties['Schema\\Alter\\DropColumn'] = [
        'schema' => ['Identifier'], 'table' => ['Identifier'], 'heading' => ['array'], 'slot' => ['int'],
    ];
    $properties['Schema\\Alter\\DropMember'] = ['name' => ['Identifier'], 'generated' => ['bool'], 'protected' => ['bool']];
    $methods['Schema\\Alter\\DropColumn'] = $methods['Select'];
    $methods['Schema\\Alter\\DropMember'] = ['__construct'];
    $roots[] = 'Schema\\Alter\\DropColumn';
    $internal[] = 'Schema\\Alter\\DropColumnLowering';
    $indexOperands = array_values(array_diff($operands, ['Parameter']));
    $properties['Schema\\Index\\CreateIndex'] = [
        'schema' => ['Identifier'], 'index' => ['Identifier'], 'table' => ['Identifier'], 'heading' => ['array'],
        'terms' => ['array'], 'where' => [...$indexOperands, 'null'], 'unique' => ['bool'], 'ifNotExists' => ['bool'],
    ];
    $properties['Schema\\Index\\IndexTerm'] = ['expression' => $indexOperands, 'descending' => ['bool']];
    $methods['Schema\\Index\\CreateIndex'] = $methods['Select'];
    $methods['Schema\\Index\\IndexTerm'] = ['__construct'];
    $roots[] = 'Schema\\Index\\CreateIndex';
    $internal[] = 'Schema\\Index\\CreateIndexLowering';
    $properties['Schema\\Index\\DropIndex'] = ['target' => ['Schema\\Index\\IndexReference', 'Schema\\Index\\MissingIndex'], 'ifExists' => ['bool']];
    $properties['Schema\\Index\\IndexReference'] = ['schema' => ['Identifier'], 'name' => ['Identifier'], 'table' => ['Identifier']];
    $properties['Schema\\Index\\MissingIndex'] = ['schema' => ['?Identifier'], 'name' => ['Identifier']];
    $methods['Schema\\Index\\DropIndex'] = $methods['Select'];
    $methods['Schema\\Index\\IndexReference'] = $methods['Schema\\Index\\MissingIndex'] = ['__construct', 'quoted'];
    $roots[] = 'Schema\\Index\\DropIndex';
    $internal[] = 'Schema\\Index\\DropIndexLowering';
    $properties['Maintenance\\Reindex'] = ['target' => ['Collation', 'Maintenance\\IndexTarget', 'Maintenance\\RelationTarget', 'null']];
    $properties['Maintenance\\RelationTarget'] = ['schema' => ['Identifier'], 'name' => ['Identifier'], 'kind' => ['From\\ExternalRelationKind']];
    $properties['Maintenance\\IndexTarget'] = ['schema' => ['Identifier'], 'name' => ['Identifier'], 'table' => ['Identifier']];
    $methods['Maintenance\\Reindex'] = $methods['Select'];
    $methods['Maintenance\\RelationTarget'] = $methods['Maintenance\\IndexTarget'] = ['__construct', 'quoted'];
    $roots[] = 'Maintenance\\Reindex';
    $properties['Maintenance\\Analyze'] = ['target' => ['Identifier', 'Maintenance\\IndexTarget', 'Maintenance\\RelationTarget', 'null']];
    $methods['Maintenance\\Analyze'] = $methods['Select'];
    $roots[] = 'Maintenance\\Analyze';
    $properties['Maintenance\\FlagPragma'] = ['name' => ['Maintenance\\FlagName'], 'change' => ['?Maintenance\\FlagChange'], 'schema' => ['?Identifier']];
    $methods['Maintenance\\FlagPragma'] = $methods['Select'];
    $roots[] = 'Maintenance\\FlagPragma';
    $properties['Maintenance\\Inventory\\InventoryPragma'] = ['kind' => ['Maintenance\\Inventory\\InventoryKind'], 'schema' => ['?Identifier']];
    $properties['Maintenance\\Inventory\\InventoryKind'] = $methods['Maintenance\\Inventory\\InventoryKind'] = [];
    $methods['Maintenance\\Inventory\\InventoryPragma'] = $methods['Select'];
    $enumCases['Maintenance\\Inventory\\InventoryKind'] = [
        'Databases' => 'database_list', 'Collations' => 'collation_list', 'CompileOptions' => 'compile_options',
        'Functions' => 'function_list', 'Modules' => 'module_list', 'Pragmas' => 'pragma_list',
    ];
    $roots[] = 'Maintenance\\Inventory\\InventoryPragma';
    $properties['Maintenance\\Metadata\\IndexInspection'] = [
        'kind' => ['Maintenance\\Metadata\\IndexInspectionKind'], 'target' => ['Maintenance\\IndexTarget', 'Maintenance\\Metadata\\EmptyIndexMetadata'],
    ];
    $properties['Maintenance\\Metadata\\EmptyIndexMetadata'] = ['schema' => ['?Identifier'], 'name' => ['?Identifier']];
    $properties['Maintenance\\Metadata\\IndexInspectionKind'] = $methods['Maintenance\\Metadata\\IndexInspectionKind'] = [];
    $methods['Maintenance\\Metadata\\IndexInspection'] = $methods['Select'];
    $methods['Maintenance\\Metadata\\EmptyIndexMetadata'] = ['__construct'];
    $enumCases['Maintenance\\Metadata\\IndexInspectionKind'] = ['Basic' => 'index_info', 'Extended' => 'index_xinfo'];
    $roots[] = 'Maintenance\\Metadata\\IndexInspection';
    $properties['Maintenance\\Metadata\\ForeignKeyAudit'] = [
        'target' => ['Identifier', 'Maintenance\\Metadata\\CatalogTable', 'Maintenance\\RelationTarget'],
        'catalogAccess' => ['?Identifier'],
    ];
    $methods['Maintenance\\Metadata\\ForeignKeyAudit'] = $methods['Select'];
    $roots[] = 'Maintenance\\Metadata\\ForeignKeyAudit';
    $internal[] = 'Maintenance\\Metadata\\ForeignKeyAuditLowering';
    $properties['Maintenance\\Integrity\\IntegrityAudit'] = [
        'kind' => ['Maintenance\\Integrity\\IntegrityKind'],
        'target' => ['Maintenance\\Integrity\\IntegrityLimit', 'Maintenance\\Metadata\\CatalogTable', 'Maintenance\\RelationTarget'],
        'schema' => ['?Identifier'],
    ];
    $properties['Maintenance\\Integrity\\IntegrityLimit'] = ['value' => ['int']];
    $properties['Maintenance\\Integrity\\IntegrityKind'] = $methods['Maintenance\\Integrity\\IntegrityKind'] = [];
    $methods['Maintenance\\Integrity\\IntegrityAudit'] = $methods['Select'];
    $methods['Maintenance\\Integrity\\IntegrityLimit'] = ['__construct'];
    $enumCases['Maintenance\\Integrity\\IntegrityKind'] = ['Full' => 'integrity_check', 'Quick' => 'quick_check'];
    $roots[] = 'Maintenance\\Integrity\\IntegrityAudit';
    $internal[] = 'Maintenance\\Integrity\\IntegrityAuditLowering';
    $internal[] = 'Maintenance\\Integrity\\IntegrityNumber';
    $properties['Maintenance\\Header\\HeaderPragma'] = [
        'field' => ['Maintenance\\Header\\HeaderCookie', 'Maintenance\\Header\\HeaderReadField'],
        'schema' => ['Identifier'], 'value' => ['?Maintenance\\Header\\HeaderInteger'],
    ];
    $properties['Maintenance\\Header\\HeaderInteger'] = ['value' => ['int']];
    $properties['Maintenance\\Header\\HeaderCookie'] = $methods['Maintenance\\Header\\HeaderCookie'] = [];
    $properties['Maintenance\\Header\\HeaderReadField'] = $methods['Maintenance\\Header\\HeaderReadField'] = [];
    $methods['Maintenance\\Header\\HeaderPragma'] = $methods['Select'];
    $methods['Maintenance\\Header\\HeaderInteger'] = ['__construct'];
    $enumCases['Maintenance\\Header\\HeaderCookie'] = ['ApplicationId' => 'application_id', 'UserVersion' => 'user_version', 'SchemaVersion' => 'schema_version'];
    $enumCases['Maintenance\\Header\\HeaderReadField'] = ['FreeListCount' => 'freelist_count', 'DataVersion' => 'data_version'];
    $roots[] = 'Maintenance\\Header\\HeaderPragma';
    $internal[] = 'Maintenance\\Header\\HeaderPragmaLowering';
    $internal[] = 'Maintenance\\Argument\\NativePragmaInteger';
    $properties['Maintenance\\Vacuum\\Vacuum'] = [
        'schema' => ['Identifier'], 'destination' => ['Maintenance\\Vacuum\\VacuumDestination', 'Maintenance\\Vacuum\\VacuumRegistrations', 'null'],
    ];
    $properties['Maintenance\\Vacuum\\VacuumDestination'] = ['expression' => ['Rows\\ValueRow']];
    $properties['Maintenance\\Vacuum\\VacuumRegistrations'] = ['bindings' => ['array']];
    $methods['Maintenance\\Vacuum\\Vacuum'] = $methods['Select'];
    $methods['Maintenance\\Vacuum\\VacuumDestination'] = $methods['Maintenance\\Vacuum\\VacuumRegistrations'] = ['__construct', 'toString'];
    $roots[] = 'Maintenance\\Vacuum\\Vacuum';
    $internal[] = 'Maintenance\\Vacuum\\VacuumLowering';
    $internal[] = 'Expression\\Binding\\NumberedRegistrations';
    $properties['Connection\\Attach'] = [
        'file' => ['Rows\\ValueRow'], 'name' => ['Connection\\AttachmentName', 'Parameter'], 'key' => ['?Rows\\ValueRow'],
    ];
    $properties['Connection\\Detach'] = ['target' => ['Connection\\DetachTarget', 'Parameter']];
    $properties['Connection\\AttachmentName'] = [
        'literal' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral'], 'name' => ['Identifier'],
    ];
    $properties['Connection\\DetachTarget'] = ['name' => ['Connection\\AttachmentName'], 'schema' => ['Identifier']];
    $methods['Connection\\Attach'] = $methods['Connection\\Detach'] = $methods['Select'];
    $methods['Connection\\AttachmentName'] = ['__construct', 'toString'];
    $methods['Connection\\DetachTarget'] = ['__construct'];
    array_push($roots, 'Connection\\Attach', 'Connection\\Detach');
    array_push($internal, 'Connection\\ConnectionOperands', 'Connection\\ConnectionLowering');
    $properties['Maintenance\\Page\\PagePragma'] = [
        'kind' => ['Maintenance\\Page\\PagePragmaKind'], 'schema' => ['Identifier'],
        'value' => ['Maintenance\\Page\\PageLimit', 'Maintenance\\Page\\RequestedPageSize', 'null'],
    ];
    $properties['Maintenance\\Page\\PageLimit'] = ['decimal' => ['string']];
    $properties['Maintenance\\Page\\RequestedPageSize'] = ['bytes' => ['int']];
    $properties['Maintenance\\Page\\PagePragmaKind'] = $methods['Maintenance\\Page\\PagePragmaKind'] = [];
    $methods['Maintenance\\Page\\PagePragma'] = $methods['Select'];
    $methods['Maintenance\\Page\\PageLimit'] = $methods['Maintenance\\Page\\RequestedPageSize'] = ['__construct'];
    $enumCases['Maintenance\\Page\\PagePragmaKind'] = ['Size' => 'page_size', 'Count' => 'page_count', 'MaximumCount' => 'max_page_count'];
    $roots[] = 'Maintenance\\Page\\PagePragma';
    array_push($internal, 'Maintenance\\Page\\PagePragmaLowering', 'Maintenance\\Page\\PageLimitArgument');
    $properties['Maintenance\\Reclaim\\AutoVacuum'] = ['schema' => ['Identifier'], 'mode' => ['?Maintenance\\Reclaim\\VacuumMode']];
    $properties['Maintenance\\Reclaim\\IncrementalVacuum'] = ['schema' => ['Identifier'], 'limit' => ['Maintenance\\Reclaim\\PageReclaimLimit']];
    $properties['Maintenance\\Reclaim\\PageReclaimLimit'] = ['pages' => ['int']];
    $properties['Maintenance\\Reclaim\\VacuumMode'] = $methods['Maintenance\\Reclaim\\VacuumMode'] = [];
    $methods['Maintenance\\Reclaim\\AutoVacuum'] = $methods['Maintenance\\Reclaim\\IncrementalVacuum'] = $methods['Select'];
    $methods['Maintenance\\Reclaim\\PageReclaimLimit'] = ['__construct'];
    $enumCases['Maintenance\\Reclaim\\VacuumMode'] = ['None' => '0', 'Full' => '1', 'Incremental' => '2'];
    array_push($roots, 'Maintenance\\Reclaim\\AutoVacuum', 'Maintenance\\Reclaim\\IncrementalVacuum');
    $internal[] = 'Maintenance\\Reclaim\\ReclaimPragmaLowering';
    $properties['Maintenance\\Cache\\CacheSize'] = ['schema' => ['Identifier'], 'amount' => ['?Maintenance\\Cache\\CacheAmount']];
    $properties['Maintenance\\Cache\\DefaultCacheSize'] = ['schema' => ['Identifier'], 'capacity' => ['?Maintenance\\Cache\\DefaultCachePages']];
    $properties['Maintenance\\Cache\\CacheSpill'] = [
        'schema' => ['Identifier'], 'change' => ['Maintenance\\Cache\\SpillMode', 'Maintenance\\Cache\\SpillThreshold', 'null'],
    ];
    $properties['Maintenance\\Cache\\CacheAmount'] = ['value' => ['int']];
    $properties['Maintenance\\Cache\\DefaultCachePages'] = ['pages' => ['int']];
    $properties['Maintenance\\Cache\\SpillThreshold'] = ['amount' => ['Maintenance\\Cache\\CacheAmount'], 'mode' => ['Maintenance\\Cache\\SpillMode']];
    $properties['Maintenance\\Cache\\SpillMode'] = $methods['Maintenance\\Cache\\SpillMode'] = [];
    $methods['Maintenance\\Cache\\CacheSize'] = $methods['Maintenance\\Cache\\DefaultCacheSize'] = $methods['Maintenance\\Cache\\CacheSpill'] = $methods['Select'];
    $methods['Maintenance\\Cache\\CacheAmount'] = $methods['Maintenance\\Cache\\DefaultCachePages'] = ['__construct'];
    $methods['Maintenance\\Cache\\SpillThreshold'] = ['__construct', 'sqlArgument'];
    $enumCases['Maintenance\\Cache\\SpillMode'] = ['Enabled' => 'ON', 'Disabled' => 'OFF'];
    array_push($roots, 'Maintenance\\Cache\\CacheSize', 'Maintenance\\Cache\\CacheSpill', 'Maintenance\\Cache\\DefaultCacheSize');
    array_push($internal, 'Maintenance\\Cache\\CachePragmaLowering', 'Maintenance\\Cache\\SpillArgument');
    $properties['Schema\\Trigger\\CreateTrigger'] = [
        'schema' => ['Identifier'], 'name' => ['Identifier'], 'placement' => ['Schema\\Trigger\\TriggerPlacement'],
        'watched' => ['TableSource'], 'timing' => ['Schema\\Trigger\\TriggerTiming'], 'event' => ['Schema\\Trigger\\TriggerEvent'],
        'program' => ['array'], 'selectors' => ['array'], 'condition' => ['?Schema\\Trigger\\TriggerCondition'], 'ifNotExists' => ['bool'],
    ];
    $properties['Schema\\Trigger\\TriggerField'] = ['image' => ['Schema\\Trigger\\RowImage'], 'column' => ['int']];
    $properties['Schema\\Trigger\\TriggerCondition'] = ['field' => ['Schema\\Trigger\\TriggerField'], 'isNull' => ['bool']];
    $properties['Schema\\Trigger\\TriggerDestination'] = ['table' => ['TableSource'], 'writable' => ['array']];
    $properties['Schema\\Trigger\\TriggerInsert'] = [
        'destination' => ['Schema\\Trigger\\TriggerDestination'], 'rows' => ['array'], 'conflict' => ['Mutation\\InsertConflict'],
    ];
    $properties['Schema\\Trigger\\TriggerInsertField'] = [
        'column' => ['int'], 'value' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Schema\\Trigger\\TriggerField'],
    ];
    $properties['Schema\\Trigger\\TriggerUpdateName'] = ['selector' => ['Identifier', 'int']];
    $properties['Schema\\Trigger\\TriggerInsertRow'] = ['fields' => ['array']];
    $methods['Schema\\Trigger\\TriggerInsertRow'] = ['__construct', 'hasSameHeading'];
    $methods['Schema\\Trigger\\CreateTrigger'] = $methods['Select'];
    foreach (['TriggerField', 'TriggerCondition', 'TriggerDestination', 'TriggerInsert', 'TriggerInsertField', 'TriggerUpdateName'] as $role) {
        $methods['Schema\\Trigger\\' . $role] = ['__construct'];
    }
    $enumCases['Schema\\Trigger\\RowImage'] = ['New' => 'NEW', 'Old' => 'OLD'];
    $enumCases['Schema\\Trigger\\TriggerEvent'] = ['Insert' => 'INSERT', 'Update' => 'UPDATE', 'Delete' => 'DELETE'];
    $enumCases['Schema\\Trigger\\TriggerPlacement'] = ['Stored' => 'stored', 'NamedTemporary' => 'named-temporary', 'TemporaryKeyword' => 'temporary-keyword'];
    $enumCases['Schema\\Trigger\\TriggerTiming'] = ['After' => 'AFTER', 'Before' => 'BEFORE', 'InsteadOf' => 'INSTEAD OF'];
    foreach (['RowImage', 'TriggerEvent', 'TriggerPlacement', 'TriggerTiming'] as $role) {
        $properties['Schema\\Trigger\\' . $role] = $methods['Schema\\Trigger\\' . $role] = [];
    }
    $roots[] = 'Schema\\Trigger\\CreateTrigger';
    $properties['Schema\\Trigger\\DropTrigger'] = [
        'target' => ['Schema\\Trigger\\Removal\\MissingTrigger', 'Schema\\Trigger\\Removal\\TriggerReference'], 'ifExists' => ['bool'],
    ];
    $properties['Schema\\Trigger\\Removal\\TriggerReference'] = [
        'schema' => ['Identifier'], 'name' => ['Identifier'], 'watchedSchema' => ['Identifier'], 'watchedName' => ['Identifier'],
    ];
    $properties['Schema\\Trigger\\Removal\\MissingTrigger'] = [
        'name' => ['Identifier'], 'verification' => ['Schema\\Trigger\\Removal\\TriggerVerification'], 'schema' => ['?Identifier'],
    ];
    $properties['Schema\\Trigger\\Removal\\TriggerVerification'] = $methods['Schema\\Trigger\\Removal\\TriggerVerification'] = [];
    $enumCases['Schema\\Trigger\\Removal\\TriggerVerification'] = [
        'SearchPath' => 'search-path', 'KnownSchema' => 'known-schema', 'UnknownSchema' => 'unknown-schema',
    ];
    $methods['Schema\\Trigger\\DropTrigger'] = $methods['Select'];
    $methods['Schema\\Trigger\\Removal\\TriggerReference'] = $methods['Schema\\Trigger\\Removal\\MissingTrigger'] = ['__construct', 'quoted'];
    $roots[] = 'Schema\\Trigger\\DropTrigger';
    $internal[] = 'Schema\\Trigger\\Binding\\DropTriggerLowering';
    $properties['Schema\\Trigger\\Program\\TriggerRaise'] = [
        'policy' => ['Schema\\Trigger\\Program\\TriggerRaisePolicy'],
        'message' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Schema\\Trigger\\TriggerField', 'null'],
        'where' => ['?Schema\\Trigger\\TriggerCondition'],
    ];
    $methods['Schema\\Trigger\\Program\\TriggerRaise'] = ['__construct'];
    $properties['Schema\\Trigger\\Program\\TriggerRaisePolicy'] = $methods['Schema\\Trigger\\Program\\TriggerRaisePolicy'] = [];
    $enumCases['Schema\\Trigger\\Program\\TriggerRaisePolicy'] = ['Ignore' => 'IGNORE', 'Fail' => 'FAIL', 'Abort' => 'ABORT', 'Rollback' => 'ROLLBACK'];
    $internal[] = 'Schema\\Trigger\\Binding\\TriggerRaiseLowering';
    $mutationPrefix = 'Schema\\Trigger\\Mutation\\';
    $mutationFilter = [$mutationPrefix . 'TriggerEquality', $mutationPrefix . 'TriggerTargetNullTest', 'Schema\\Trigger\\TriggerCondition', 'null'];
    $properties[$mutationPrefix . 'TriggerAssignment'] = [
        'column' => ['int'], 'value' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', $mutationPrefix . 'TriggerTargetField', 'Schema\\Trigger\\TriggerField'],
    ];
    $properties[$mutationPrefix . 'TriggerTargetField'] = ['column' => ['int']];
    $properties[$mutationPrefix . 'TriggerEquality'] = ['target' => [$mutationPrefix . 'TriggerTargetField'], 'image' => ['Schema\\Trigger\\TriggerField'], 'targetFirst' => ['bool']];
    $properties[$mutationPrefix . 'TriggerTargetNullTest'] = ['target' => [$mutationPrefix . 'TriggerTargetField'], 'isNull' => ['bool']];
    $properties[$mutationPrefix . 'TriggerUpdate'] = [
        'destination' => ['Schema\\Trigger\\TriggerDestination'], 'assignments' => ['array'], 'where' => $mutationFilter, 'conflict' => ['Mutation\\UpdateConflict'],
    ];
    $properties[$mutationPrefix . 'TriggerDelete'] = ['destination' => ['Schema\\Trigger\\TriggerDestination'], 'where' => $mutationFilter];
    foreach (['TriggerAssignment', 'TriggerTargetField', 'TriggerEquality', 'TriggerTargetNullTest', 'TriggerUpdate', 'TriggerDelete'] as $role) {
        $methods[$mutationPrefix . $role] = ['__construct'];
    }
    $mutationHelpers = array_map(static fn ($role) => $mutationPrefix . $role, ['TriggerActionFields', 'TriggerActions', 'TriggerMutationScope', 'TriggerMutationSql']);
    array_push($pureCollaborators, ...$mutationHelpers);
    array_push($internal, ...$mutationHelpers);
    array_push($internal, 'Schema\\Trigger\\Binding\\TriggerMutationLowering', 'Schema\\Trigger\\Binding\\TriggerMutationOperands',
        'Schema\\Trigger\\Binding\\TriggerTargetLowering');
    $internal[] = 'Schema\\DefinitionLowering';
    array_push($internal, 'Schema\\Trigger\\TriggerSql', 'Schema\\Trigger\\TriggerChecks',
        'Schema\\Trigger\\Binding\\TriggerLowering', 'Schema\\Trigger\\Binding\\TriggerStepLowering', 'Schema\\Trigger\\Binding\\TriggerOperands');
    array_push($pureCollaborators, 'Schema\\Trigger\\TriggerSql', 'Schema\\Trigger\\TriggerChecks');
    $internal[] = 'Maintenance\\Metadata\\IndexMetadataLowering';
    $internal[] = 'Maintenance\\PragmaScope';
    $properties['Maintenance\\Metadata\\TableInspection'] = ['kind' => ['Maintenance\\Metadata\\TableInspectionKind'],
        'target' => ['Maintenance\\Metadata\\CatalogTable', 'Maintenance\\Metadata\\MissingMetadataTable', 'Maintenance\\RelationTarget', 'null'],
        'schema' => ['?Identifier'],
    ];
    $properties['Maintenance\\Metadata\\TableListing'] = ['schema' => ['?Identifier'], 'nameFilter' => ['?Identifier']];
    $methods['Maintenance\\Metadata\\TableInspection'] = $methods['Maintenance\\Metadata\\TableListing'] = $methods['Select'];
    $properties['Maintenance\\Metadata\\CatalogTable'] = ['schema' => ['Identifier']];
    $methods['Maintenance\\Metadata\\CatalogTable'] = ['__construct', 'name'];
    $properties['Maintenance\\Metadata\\MissingMetadataTable'] = ['name' => ['Identifier']];
    $methods['Maintenance\\Metadata\\MissingMetadataTable'] = ['__construct'];
    $properties['Maintenance\\Metadata\\TableInspectionKind'] = $methods['Maintenance\\Metadata\\TableInspectionKind'] = [];
    $enumCases['Maintenance\\Metadata\\TableInspectionKind'] = [
        'Columns' => 'table_info', 'ExtendedColumns' => 'table_xinfo', 'Indexes' => 'index_list', 'ForeignKeys' => 'foreign_key_list',
    ];
    array_push($roots, 'Maintenance\\Metadata\\TableInspection', 'Maintenance\\Metadata\\TableListing');
    array_push($internal, 'Maintenance\\Metadata\\CatalogAliases', 'Maintenance\\Metadata\\TableMetadataLowering', 'Maintenance\\PragmaLowering');
    $pureCollaborators[] = 'Maintenance\\Metadata\\CatalogAliases';
    $properties['Maintenance\\FlagName'] = $methods['Maintenance\\FlagName'] = [];
    $properties['Maintenance\\FlagChange'] = $methods['Maintenance\\FlagChange'] = [];
    $enumCases['Maintenance\\FlagChange'] = ['Disable' => '0', 'Enable' => '1', 'Reset' => 'RESET'];
    $enumCases['Maintenance\\FlagName'] = [
        'AutomaticIndex' => 'automatic_index',
        'CellSizeCheck' => 'cell_size_check',
        'CheckpointFullfsync' => 'checkpoint_fullfsync',
        'CountChanges' => 'count_changes',
        'DeferForeignKeys' => 'defer_foreign_keys',
        'EmptyResultCallbacks' => 'empty_result_callbacks',
        'ForeignKeys' => 'foreign_keys',
        'FullColumnNames' => 'full_column_names',
        'Fullfsync' => 'fullfsync',
        'IgnoreCheckConstraints' => 'ignore_check_constraints',
        'LegacyAlterTable' => 'legacy_alter_table',
        'QueryOnly' => 'query_only',
        'ReadUncommitted' => 'read_uncommitted',
        'RecursiveTriggers' => 'recursive_triggers',
        'ReverseUnorderedSelects' => 'reverse_unordered_selects',
        'ShortColumnNames' => 'short_column_names',
        'TrustedSchema' => 'trusted_schema',
        'WritableSchema' => 'writable_schema',
    ];
    array_push($internal, 'Maintenance\\FlagArgument', 'Maintenance\\FlagPragmaLowering', 'Maintenance\\MaintenanceLowering');
    array_push($internal, 'Maintenance\\AnalyzeLowering', 'Maintenance\\MaintenanceLookup');
    $internal[] = 'Maintenance\\ReindexLowering';
    $properties['Schema\\Alter\\RenameTable'] = ['schema' => ['Identifier'], 'table' => ['Identifier'], 'destination' => ['Identifier']];
    $properties['Schema\\Alter\\RenameColumn'] = [...$properties['Schema\\Alter\\RenameTable'], 'column' => ['Identifier'], 'quoteReferences' => ['bool']];
    $methods['Schema\\Alter\\RenameTable'] = $methods['Schema\\Alter\\RenameColumn'] = $methods['Select'];
    $properties['Schema\\View\\CreateView'] = [
        'schema' => ['Identifier'], 'view' => ['Identifier'], 'columns' => ['array'],
        'query' => ['Aggregation\\GroupedSelect', 'Rows\\Compound', 'Rows\\ValuesQuery', 'Select'], 'ifNotExists' => ['bool'],
    ];
    $methods['Schema\\View\\CreateView'] = $methods['Select'];
    $roots[] = 'Schema\\View\\CreateView';
    array_push($internal, 'Schema\\View\\CreateViewLowering', 'Schema\\View\\ViewQueryBounds');
    array_push($internal, 'Schema\\View\\CompoundViewBounds', 'Schema\\View\\ViewSql');
    array_push($pureCollaborators, 'Schema\\View\\ViewQueryBounds', 'Schema\\View\\CompoundViewBounds', 'Schema\\View\\ViewSql');
    $roots[] = 'Schema\\Query\\CreateTableAs';
    array_push($internal, 'Schema\\CreateSchema', 'Schema\\Query\\CreateTableAsLowering', 'Schema\\Query\\CreateAsLayout', 'Schema\\Query\\CreateAsAffinity');
    array_push($pureCollaborators, 'Schema\\Query\\CreateAsLayout', 'Schema\\Query\\CreateAsAffinity');
    $properties['Schema\\ColumnDefaultExpression'] = ['expression' => [
        'Arithmetic', 'Expression\\CaseExpression', 'Expression\\CastExpression', 'Expression\\Concatenation',
        'Expression\\Nulls\\Coalesce', 'Expression\\UnaryExpression', 'Predicate\\FoldedMembership',
    ]];
    $methods['Schema\\ColumnDefaultExpression'] = ['__construct'];
    $properties['Schema\\TableColumn'] = [
        'name' => ['Identifier'], 'collation' => ['Collation'], 'type' => ['Schema\\DeclaredDatatypeName'], 'notNull' => ['?Schema\\NotNullConflict'],
        'default' => ['IntegerLiteral', 'Literal\\BlobLiteral', 'Literal\\HexIntegerLiteral', 'Literal\\RealLiteral',
            'Literal\\TextLiteral', 'NullLiteral', 'Schema\\ColumnDefaultExpression', 'null'],
    ];
    $properties['Schema\\TableColumn']['generated'] = ['?Schema\\Generation\\ColumnGeneration'];
    $methods['Schema\\TableColumn'] = ['__construct'];
    $properties['Schema\\Generation\\ColumnGeneration'] = [
        'mode' => ['Schema\\Generation\\Storage'], 'expression' => array_values(array_diff($operands, ['Parameter'])),
    ];
    $methods['Schema\\Generation\\ColumnGeneration'] = ['__construct'];
    $methods['Schema\\Generation\\Storage'] = $properties['Schema\\Generation\\Storage'] = [];
    $enumCases['Schema\\Generation\\Storage'] = ['Virtual' => 'VIRTUAL', 'Stored' => 'STORED'];
    array_push(
        $internal,
        'Schema\\CreateColumnLowering',
        'Schema\\DefaultLowering',
        'Schema\\DeclaredDatatypeLowering',
        'Schema\\Generation\\GeneratedLowering',
        'Schema\\Generation\\GeneratedGraph'
    );
    $pureCollaborators[] = 'Schema\\Generation\\GeneratedGraph';
    $roots[] = 'Mutation\\Delete';
    $roots[] = 'Mutation\\Insert';
    $roots[] = 'Mutation\\Update';
    $properties['Rows\\Compound'] = ['first' => ['Rows\\ValuesQuery', 'Select'], 'limit' => ['?Ordering\\Limit'], 'orderBy' => ['array'], 'steps' => ['array']];
    $properties['Rows\\CompoundStep'] = ['operator' => ['Rows\\CompoundOperator'], 'query' => ['Rows\\ValuesQuery', 'Select']];
    $properties['Rows\\CompoundOperator'] = [];
    if ($dialect === 'postgres') {
        $setArms = ['Rows\\Compound', 'Rows\\Values\\ValuesQuery', 'Select'];
        $properties['Rows\\Compound']['left'] = $properties['Rows\\Compound']['right'] = $setArms;
    }
    $methods['Rows\\Compound'] = $methods['Select'];
    $methods['Rows\\CompoundStep'] = ['__construct'];
    $methods['Rows\\CompoundOperator'] = [];
    $roots[] = 'Rows\\Compound';
    array_push($internal, 'Expression\\BinaryOperatorLowering', 'Literal\\NumericLiteralLowering', 'Rows\\CompoundBounds', 'Rows\\CompoundLowering');
    $pureCollaborators[] = 'Rows\\CompoundBounds';
    $enumCases['Rows\\CompoundOperator'] = ['UnionAll' => 'UNION ALL', 'Union' => 'UNION', 'Intersect' => 'INTERSECT', 'Except' => 'EXCEPT'];
    $roots[] = 'Rows\\ValuesQuery';
    $roots[] = 'Schema\\DropTable';
    $roots[] = 'Schema\\DropView';
    $roots[] = 'Schema\\CreateTable';
    $properties['Predicate\\Comparison'] = ['operator' => ['Predicate\\ComparisonOperator'], 'left' => $operands, 'right' => $operands];
    $properties['Predicate\\Logical'] = ['operator' => ['Predicate\\LogicalOperator'], 'left' => $predicates, 'right' => $predicates];
    $enumCases['From\\JoinKind'] = ['Inner' => 'INNER', 'Cross' => 'CROSS', 'Left' => 'LEFT', 'Right' => 'RIGHT', 'Full' => 'FULL'];
    $enumCases['Mutation\\InsertConflictHandler'] = ['None' => 'none', 'DoNothing' => 'do-nothing'];
    $enumCases['Mutation\\InsertConflict'] = [
        'Inherit' => 'inherit', 'Abort' => 'abort', 'Ignore' => 'ignore', 'Fail' => 'fail', 'Rollback' => 'rollback', 'Replace' => 'replace',
    ];
    $enumCases['Mutation\\UpdateConflict'] = [
        'Inherit' => 'inherit', 'Abort' => 'abort', 'Ignore' => 'ignore', 'Fail' => 'fail', 'Rollback' => 'rollback', 'Replace' => 'replace',
    ];
    $enumCases['Schema\\NotNullConflict'] = ['Abort' => 'ABORT', 'Fail' => 'FAIL', 'Ignore' => 'IGNORE',
        'Replace' => 'REPLACE', 'Rollback' => 'ROLLBACK'];
    $enumCases['Schema\\DeclaredDatatypeName'] = ['Untyped' => '', 'Int' => 'INT', 'Integer' => 'INTEGER', 'Text' => 'TEXT', 'Real' => 'REAL', 'Blob' => 'BLOB', 'Any' => 'ANY'];
    $enumCases['Predicate\\ComparisonOperator'] = [
        'Equal' => '=', 'NotEqual' => '<>', 'Less' => '<', 'LessOrEqual' => '<=',
        'Greater' => '>', 'GreaterOrEqual' => '>=', 'Is' => 'IS', 'IsNot' => 'IS NOT',
    ];
    $enumCases['Predicate\\LogicalOperator'] = ['And' => 'AND', 'Or' => 'OR'];
    foreach (['From\\JoinKind', 'Mutation\\InsertConflict', 'Mutation\\InsertConflictHandler', 'Mutation\\UpdateConflict', 'Predicate\\ComparisonOperator', 'Predicate\\LogicalOperator', 'Schema\\DeclaredDatatypeName', 'Schema\\NotNullConflict'] as $enum) {
        $methods[$enum] = $properties[$enum] = [];
    }
    foreach (['From\\Join', 'Ordering\\Order', 'Ordering\\Limit', 'Predicate\\Comparison', 'Predicate\\Logical'] as $value) {
        $methods[$value] = ['__construct'];
    }
    array_push($internal, 'From\\SourceDeclarations', 'From\\QueryClauses', 'From\\JoinKeyLowering', 'Mutation\\DeleteLowering', 'Mutation\\InsertLowering', 'Mutation\\UpdateLowering', 'Predicate\\ExpressionScope', 'Rows\\ValuesLowering', 'Rows\\LocalSourceLowering', 'Schema\\DropLowering', 'Schema\\CreateTableLowering');
    $pureCollaborators[] = 'Predicate\\ExpressionScope';
    $internal[] = 'Rows\\LocalInputs';
    $pureCollaborators[] = 'Rows\\LocalInputs';
    $roots[] = 'Transaction\\Transaction';
    $internal[] = 'Transaction\\TransactionLowering';
    $properties['Transaction\\Transaction'] = ['command' => ['Transaction\\TransactionCommand'], 'name' => ['?Transaction\\SavepointName']];
    $properties['Transaction\\SavepointName'] = ['value' => ['string']];
    $properties['Transaction\\TransactionCommand'] = [];
    $methods['Transaction\\Transaction'] = $methods['Select'];
    $methods['Transaction\\SavepointName'] = ['__construct', 'quoted'];
    $methods['Transaction\\TransactionCommand'] = [];
    $enumCases['Transaction\\TransactionCommand'] = [
        'BeginDeferred' => 'BEGIN DEFERRED', 'BeginImmediate' => 'BEGIN IMMEDIATE', 'BeginExclusive' => 'BEGIN EXCLUSIVE',
        'Commit' => 'COMMIT', 'Rollback' => 'ROLLBACK', 'Savepoint' => 'SAVEPOINT', 'Release' => 'RELEASE SAVEPOINT',
        'RollbackTo' => 'ROLLBACK TO SAVEPOINT',
    ];
}
if ($dialect !== 'sqlite') {
    if ($dialect === 'mysql') {
        array_push($internal, 'Literal\\TextLiteralLowering', 'Ordering\\ProjectionLabel', 'Ordering\\ProjectionColumns');
        $properties['Literal\\TextLiteral'] = ['value' => ['string']];
        $methods['Literal\\TextLiteral'] = ['__construct', 'toString'];
        $properties['ResultColumn']['expression'][] = 'Literal\\TextLiteral';
        sort($properties['ResultColumn']['expression']);
    }
    $internal[] = 'Binding\\ParameterLowering';
    array_push($roots, 'Aggregation\\GroupedSelect', 'Rows\\Compound', 'Rows\\LocalProjection', 'Rows\\RecursiveSeries', 'Mutation\\Delete', 'Mutation\\Insert', 'Mutation\\Update', 'Schema\\CreateTable', 'Schema\\DropTable', 'Transaction\\Transaction');
    $properties['Aggregation\\GroupedSelect'] = [
        'source' => ['TableSource'], 'keys' => ['array'], 'columns' => ['array'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $methods['Aggregation\\GroupedSelect'] = $methods['Select'];
    $properties['Aggregation\\AggregateOperator'] = $methods['Aggregation\\AggregateOperator'] = [];
    $enumCases['Aggregation\\AggregateOperator'] = ['Count' => 'count', 'Sum' => 'sum', 'Min' => 'min', 'Max' => 'max'];
    $properties['Aggregation\\Aggregate'] = ['operator' => ['Aggregation\\AggregateOperator'], 'argument' => ['?ColumnReference'], 'distinct' => ['bool']];
    if ($dialect === 'mysql') {
        $properties['Aggregation\\Aggregate']['argument'] = ['ColumnReference', 'From\\TextColumnReference', 'null'];
    }
    $properties['Aggregation\\GroupColumn'] = ['value' => ['Aggregation\\Aggregate', 'ColumnReference'], 'name' => ['Identifier']];
    $methods['Aggregation\\Aggregate'] = $methods['Aggregation\\GroupColumn'] = ['__construct'];
    array_push($internal, 'Aggregation\\AggregateBinding', 'Aggregation\\GroupedKeys', 'Aggregation\\GroupedLowering', 'Aggregation\\GroupedSql');
    $pureCollaborators[] = 'Aggregation\\GroupedSql';
    $properties['ColumnReference']['occurrence'] = ['int'];
    $truthValues = ['BooleanLiteral', 'NullLiteral', 'Predicate\\IntegerComparison', 'Predicate\\LogicalPredicate', 'Predicate\\NullTest'];
    $truthValues[] = 'Predicate\\IntegerRange';
    if ($dialect === 'postgres') {
        array_push($truthValues, 'From\\BooleanColumnReference', 'Predicate\\IntegerDistinctness', 'Predicate\\Text\\TextComparison', 'Predicate\\TruthTest');
    } else {
        $truthValues[] = 'Predicate\\Text\\TextComparison';
    }
    sort($truthValues);
    if ($dialect === 'postgres') {
        $properties['Predicate\\TruthTest'] = ['operator' => ['Predicate\\TruthOperator'], 'operand' => $truthValues];
        $enumCases['Predicate\\TruthOperator'] = [
            'IsTrue' => 'IS TRUE', 'IsNotTrue' => 'IS NOT TRUE',
            'IsFalse' => 'IS FALSE', 'IsNotFalse' => 'IS NOT FALSE',
            'IsUnknown' => 'IS UNKNOWN', 'IsNotUnknown' => 'IS NOT UNKNOWN',
        ];
    }
    $conditions = [...$truthValues, 'null'];
    $properties['Aggregation\\GroupedSelect']['where'] = $conditions;
    if ($dialect === 'postgres') {
        $properties['Aggregation\\GroupedSelect']['orderBy'] = ['array'];
        $properties['Aggregation\\GroupedSelect']['limit'] = ['?Ordering\\Limit'];
    }
    if ($dialect === 'mysql' || $dialect === 'postgres') {
        $properties['Aggregation\\GroupedSelect']['having'] = ['Aggregation\\GroupedComparison', 'BooleanLiteral', 'NullLiteral', 'null'];
        $groupTerms =  ['Aggregation\\Aggregate', 'ColumnReference', $dialect === 'mysql' ? 'Predicate\\RangeIntegerLiteral' : 'Expression\\WideIntegerLiteral', 'IntegerLiteral'];
        sort($groupTerms);
        $properties['Aggregation\\GroupedComparison'] = ['operator' => ['Predicate\\IntegerOperator'], 'left' => $groupTerms, 'right' => $groupTerms];
        $methods['Aggregation\\GroupedComparison'] = ['__construct'];
        $internal[] = 'Aggregation\\HavingLowering';
    }
    $properties['Select']['source'] = ['From\\Join', 'TableSource', 'null'];
    $properties['Select']['where'] = $conditions;
    $properties['Select']['distinct'] = ['bool'];
    $properties['Select']['orderBy'] = ['array'];
    $properties['Select']['limit'] = ['?Ordering\\Limit'];
    $properties['Ordering\\Order'] = ['position' => ['int'], 'descending' => ['bool'], 'nullsFirst' => ['bool']];
    if ($dialect === 'postgres') {
        unset($properties['Ordering\\Order']['position']);
        $properties['Ordering\\Order']['target'] = [
            'Arithmetic', 'ColumnReference', 'Expression\\BuiltinCast', 'Expression\\CollatedText',
            'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference', 'int',
        ];
    }
    $properties['Ordering\\Limit'] = ['count' => [$dialect === 'mysql' ? 'int' : '?int'], 'offset' => ['int']];
    $methods['Ordering\\Order'] = $methods['Ordering\\Limit'] = ['__construct'];
    $properties['From\\Join'] = [
        'kind' => ['From\\JoinKind'], 'left' => ['From\\DerivedSource', 'From\\Join', 'TableSource'],
        'right' => ['From\\DerivedSource', 'From\\Join', 'TableSource'], 'on' => $conditions,
    ];
    $properties['From\\DerivedSource'] = ['definition' => ['Select']];
    $methods['From\\DerivedSource'] = ['__construct', $dialect === 'postgres' ? 'columnKind' : 'columnType'];
    $methods['From\\Join'] = ['__construct', 'inputs'];
    $properties['From\\JoinKind'] = $methods['From\\JoinKind'] = [];
    $enumCases['From\\JoinKind'] = ['Cross' => 'CROSS', 'Inner' => 'INNER', 'Left' => 'LEFT', 'Right' => 'RIGHT'];
    if ($dialect === 'postgres') {
        $enumCases['From\\JoinKind']['Full'] = 'FULL';
    }
    array_push($internal, 'From\\DerivedBinding', 'From\\JoinBinding', 'From\\MergedJoinLowering', 'From\\OccurrenceScope', 'Predicate\\PredicateLowering', 'Ordering\\ResultLowering', 'Ordering\\ResultSql');
    array_push($pureCollaborators, 'From\\OccurrenceScope', 'Ordering\\ResultSql');
    $internal[] = 'Mutation\\DeleteLowering';
    $properties['Mutation\\Delete'] = [
        'target' => ['TableSource'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
        'where' => $conditions,
    ];
    if ($dialect === 'postgres') {
        $properties['Mutation\\Delete']['returning'] = ['array'];
        $properties['Mutation\\ReturnedColumn'] = ['reference' => ['ColumnReference', 'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference'], 'name' => ['Identifier']];
        $methods['Mutation\\ReturnedColumn'] = ['__construct'];
        array_push($internal, 'Mutation\\ReturningLayout', 'Mutation\\ReturningLowering', 'Mutation\\ReturningSql');
        array_push($pureCollaborators, 'Mutation\\ReturningLayout', 'Mutation\\ReturningSql');
    }
    $properties['Predicate\\IntegerComparison'] = [
        'operator' => ['Predicate\\IntegerOperator'], 'left' => ['ColumnReference', 'IntegerLiteral'], 'right' => ['ColumnReference', 'IntegerLiteral'],
    ];
    if ($dialect === 'postgres') {
        $signed = ['ColumnReference', 'Expression\\WideIntegerLiteral', 'From\\WideIntegerColumnReference', 'IntegerLiteral'];
        $properties['Predicate\\IntegerComparison']['left'] = $signed;
        $properties['Predicate\\IntegerComparison']['right'] = $signed;
    }
    $properties['Predicate\\IntegerOperator'] = [];
    $properties['Predicate\\LogicalPredicate'] = [
        'operator' => ['Predicate\\LogicalOperator'], 'left' => $truthValues, 'right' => $conditions,
    ];
    $properties['Predicate\\LogicalOperator'] = $methods['Predicate\\LogicalOperator'] = [];
    $properties['Predicate\\NullTest'] = [
        'operand' => ['BooleanLiteral', 'ColumnReference', 'IntegerLiteral', 'NullLiteral', 'Predicate\\IntegerComparison', 'Predicate\\LogicalPredicate', 'Predicate\\NullTest'],
        'negated' => ['bool'],
    ];
    array_push($properties['Predicate\\NullTest']['operand'], 'Predicate\\IntegerRange', 'From\\TextColumnReference');
    if ($dialect === 'postgres') {
        array_push($properties['Predicate\\NullTest']['operand'], 'From\\BooleanColumnReference', 'From\\WideIntegerColumnReference', 'Predicate\\IntegerDistinctness', 'Predicate\\Text\\TextComparison', 'Predicate\\TruthTest');
    } else {
        $properties['Predicate\\NullTest']['operand'][] = 'Predicate\\Text\\TextComparison';
    }
    sort($properties['Predicate\\NullTest']['operand']);
    $methods['Predicate\\LogicalPredicate'] = $methods['Predicate\\NullTest'] = ['__construct'];
    $enumCases['Predicate\\LogicalOperator'] = ['And' => 'AND', 'Or' => 'OR', 'Not' => 'NOT'];
    if ($dialect === 'mysql') {
        $enumCases['Predicate\\LogicalOperator']['Xor'] = 'XOR';
    }
    $methods['Mutation\\Delete'] = $methods['Select'];
    $properties['Mutation\\Insert'] = [
        'target' => ['TableSource'], 'rows' => ['array'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $roots[] = 'Mutation\\InsertQuery';
    $properties['Mutation\\InsertQuery'] = ['target' => ['TableSource'], 'query' => ['Select']];
    if ($dialect === 'postgres') {
        $properties['Mutation\\Insert']['target'] = $properties['Mutation\\InsertQuery']['target'] = ['Mutation\\Insert\\Destination'];
        $properties['Mutation\\Insert']['returning'] = $properties['Mutation\\InsertQuery']['returning'] = ['array'];
        $properties['Mutation\\Insert\\Destination'] = ['relation' => ['TableSource'], 'written' => ['array']];
        $methods['Mutation\\Insert\\Destination'] = ['__construct', 'toString'];
    if ($dialect === 'postgres') {
        $methods['Mutation\\Insert\\Destination'] = ['__construct', 'columnKind', 'toString'];
    }
    }
    $methods['Mutation\\InsertQuery'] = $methods['Select'];
    $internal[] = 'Mutation\\InsertTargetBinding';
    $methods['Mutation\\Insert'] = $methods['Select'];
    $properties['Mutation\\InsertRow'] = ['values' => ['array']];
    $properties['Mutation\\InsertCell'] = ['value' => ['IntegerLiteral', 'Literal\\TextLiteral', 'null']];
    if ($dialect === 'postgres') {
        $properties['Mutation\\InsertCell']['value'] = ['BooleanLiteral', 'Expression\\TextLiteral', 'Expression\\WideIntegerLiteral', 'IntegerLiteral', 'null'];
        $properties['Mutation\\Insert\\WriteSlot'] = ['slot' => ['int'], 'kind' => ['From\\ColumnKind'], 'occurrence' => ['int']];
        $methods['Mutation\\Insert\\WriteSlot'] = ['__construct'];
    }
    $methods['Mutation\\InsertRow'] = $methods['Mutation\\InsertCell'] = ['__construct', 'toString'];
    $internal[] = 'Mutation\\InsertLowering';
    $properties['Mutation\\Update'] = [
        'target' => ['TableSource'], 'assignments' => ['array'], 'where' => $conditions,
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $properties['Mutation\\UpdateAssignment'] = [
        'target' => ['ColumnReference'], 'value' => ['ColumnReference', 'IntegerLiteral', 'NullLiteral'],
    ];
    $methods['Mutation\\Update'] = $methods['Select'];
    if ($dialect === 'postgres') {
        $properties['Mutation\\Update']['returning'] = ['array'];
        array_push($properties['Mutation\\UpdateAssignment']['target'], 'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference');
        array_push($properties['Mutation\\UpdateAssignment']['value'], 'BooleanLiteral', 'Expression\\TextLiteral', 'Expression\\WideIntegerLiteral', 'From\\BooleanColumnReference', 'From\\TextColumnReference', 'From\\WideIntegerColumnReference');
        sort($properties['Mutation\\UpdateAssignment']['value']);
    }
    $methods['Mutation\\UpdateAssignment'] = ['__construct'];
    $internal[] = 'Mutation\\UpdateLowering';
    if ($dialect === 'mysql') {
        $properties['Mutation\\TextUpdateAssignment'] = [
            'target' => ['From\\TextColumnReference'], 'value' => ['From\\TextColumnReference', 'Literal\\TextLiteral', 'NullLiteral'],
        ];
        $methods['Mutation\\TextUpdateAssignment'] = ['__construct'];
        $internal[] = 'Mutation\\UpdateAssignmentLowering';
    }
    $properties['Rows\\LocalProjection'] = ['definition' => ['Rows\\LocalProjection', 'Select'], 'columns' => ['array']];
    if ($dialect === 'mysql' || $dialect === 'postgres') {
        $with = 'Rows\\With\\WithProjection';
        $withRead = 'Rows\\With\\WithRead';
        $withDefinition = 'Rows\\With\\WithDefinition';
        $withMaterialization = 'Rows\\With\\WithMaterialization';
        $properties['Rows\\LocalProjection']['definition'] = ['Rows\\LocalProjection', $with, 'Select'];
        $properties[$with] = [
            'definitions' => ['array'], 'body' => ['Rows\\LocalProjection', $withRead, 'Select'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
        ];
        $properties[$withRead] = ['definition' => ['int'], 'columns' => ['array']];
        $properties[$withDefinition] = ['body' => ['Rows\\LocalProjection', $with, $withRead, 'Select']];
        if ($dialect === 'postgres') {
            $localValues = 'Rows\\Values\\ValuesQuery';
            $properties['Rows\\LocalProjection']['definition'] = ['Rows\\LocalProjection', $localValues, $with, 'Select'];
            $properties[$with]['body'] = ['Rows\\LocalProjection', $localValues, $withRead, 'Select'];
            $properties[$withDefinition]['body'] = ['Rows\\LocalProjection', $localValues, $with, $withRead, 'Select'];
            $internal[] = 'Rows\\Values\\ValuesSql';
            $pureCollaborators[] = 'Rows\\Values\\ValuesSql';
            $properties[$withDefinition]['materialization'] = [$withMaterialization];
            $properties[$withMaterialization] = $methods[$withMaterialization] = [];
            $enumCases[$withMaterialization] = ['Default' => '', 'Materialized' => 'MATERIALIZED', 'NotMaterialized' => 'NOT MATERIALIZED'];
        }
        $methods[$with] = ['__construct', 'width', 'profileId', 'toString'];
        $methods[$withRead] = $methods[$withDefinition] = ['__construct'];
        $roots[] = $with;
        array_push($internal, 'Rows\\With\\WithScope', 'Rows\\With\\WithSql', 'Rows\\With\\WithLowering');
        array_push($pureCollaborators, 'Rows\\With\\WithScope', 'Rows\\With\\WithSql');
    }
    $properties['Rows\\LocalResultColumn'] = ['slot' => ['int'], 'name' => ['Identifier']];
    $methods['Rows\\LocalProjection'] = $methods['Select'];
    $methods['Rows\\LocalResultColumn'] = ['__construct'];
    array_push($internal, 'Rows\\LocalProjectionLowering', 'Rows\\LocalDefinition', 'Rows\\LocalProjectionSql');
    $pureCollaborators[] = 'Rows\\LocalProjectionSql';
    $properties['Rows\\RecursiveSeries'] = [
        'start' => ['IntegerLiteral'], 'increment' => ['IntegerLiteral'], 'boundary' => ['IntegerLiteral'],
        'comparison' => ['Predicate\\IntegerOperator'], 'all' => ['bool'], 'columns' => ['array'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $methods['Rows\\RecursiveSeries'] = $methods['Select'];
    array_push($internal, 'Rows\\Recursive\\RecursiveSeriesLowering', 'Rows\\Recursive\\RecursiveStepLowering', 'Rows\\Recursive\\RecursiveBinding');
    $properties['Rows\\Compound'] = [
        'left' => ['Rows\\Compound', 'Select'], 'operator' => ['Rows\\CompoundOperator'],
        'all' => ['bool'], 'right' => ['Rows\\Compound', 'Select'],
    ];
    if ($dialect === 'postgres') {
        $setArms = ['Rows\\Compound', 'Rows\\Values\\ValuesQuery', 'Select'];
        $properties['Rows\\Compound']['left'] = $properties['Rows\\Compound']['right'] = $setArms;
    }
    $methods['Rows\\Compound'] = $methods['Select'];
    $properties['Rows\\CompoundOperator'] = $methods['Rows\\CompoundOperator'] = [];
    $properties['Rows\\ResultKind'] = $methods['Rows\\ResultKind'] = [];
    $enumCases['Rows\\CompoundOperator'] = ['Union' => 'UNION', 'Intersect' => 'INTERSECT', 'Except' => 'EXCEPT'];
    $enumCases['Rows\\ResultKind'] = $dialect === 'mysql'
        ? ['Integer' => 'int32', 'WideInteger' => 'int64', 'UnsignedInteger' => 'uint64', 'DecimalInteger' => 'decimal-integer', 'Text' => 'text', 'Unknown' => 'unknown', 'NullOnly' => 'null-only']
        : ['Integer' => 'int32', 'WideInteger' => 'int64', 'Boolean' => 'bool', 'Unknown' => 'unknown', 'Text' => 'text'];
    array_push($internal, 'Rows\\CompoundLowering', 'Rows\\CompoundShape');
    $pureCollaborators[] = 'Rows\\CompoundShape';
    $properties['Schema\\CreateTable'] = [
        'target' => ['Schema\\TableName'], 'columns' => ['array'], 'ifNotExists' => ['bool'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $methods['Schema\\CreateTable'] = $methods['Select'];
    $properties['Schema\\TableColumn'] = [
        'name' => ['Identifier'], 'storage' => ['Schema\\IntegerStorage'], 'nullable' => ['bool'],
        'default' => ['IntegerLiteral', 'NullLiteral', 'null'],
    ];
    $methods['Schema\\TableColumn'] = ['__construct', 'toString'];
    $properties['Schema\\IntegerStorage'] = $methods['Schema\\IntegerStorage'] = [];
    $enumCases['Schema\\IntegerStorage'] = ['Small' => 'SMALLINT', 'Standard' => 'INTEGER', 'Wide' => 'BIGINT'];
    if ($dialect === 'postgres') {
        $properties['Schema\\CharacterStorage'] = $methods['Schema\\CharacterStorage'] = [];
        $properties['Schema\\BooleanStorage'] = $methods['Schema\\BooleanStorage'] = [];
        array_push($internal, 'From\\ProjectionLowering', 'Expression\\BooleanInput', 'Schema\\LiteralDefaultLowering', 'Schema\\BooleanDefaultLowering');
        $enumCases['Schema\\CharacterStorage'] = ['Text' => 'pg_catalog.text'];
        $enumCases['Schema\\BooleanStorage'] = ['Boolean' => 'pg_catalog.bool'];
        $properties['Schema\\TableColumn']['storage'] = ['Schema\\BooleanStorage', 'Schema\\CharacterStorage', 'Schema\\IntegerStorage'];
        $properties['Schema\\TableColumn']['default'] = ['BooleanLiteral', 'Expression\\TextLiteral', 'Expression\\WideIntegerLiteral', 'IntegerLiteral', 'NullLiteral', 'null'];
        $key = 'Schema\\Key\\';
        $properties['Schema\\CreateTable']['primaryKey'] = ['?' . $key . 'PrimaryKey'];
        $properties[$key . 'PrimaryKey'] = [
            'keys' => ['array'], 'included' => ['array'], 'name' => ['?Identifier'], 'timing' => [$key . 'KeyTiming'],
        ];
        $methods[$key . 'PrimaryKey'] = ['__construct', 'toString'];
        $properties[$key . 'KeyTiming'] = $methods[$key . 'KeyTiming'] = [];
        $enumCases[$key . 'KeyTiming'] = [
            'NotDeferrable' => 'NOT DEFERRABLE', 'InitiallyImmediate' => 'DEFERRABLE INITIALLY IMMEDIATE',
            'InitiallyDeferred' => 'DEFERRABLE INITIALLY DEFERRED',
        ];
        $properties['Schema\\CreateTable']['uniqueKeys'] = ['array'];
        $properties[$key . 'UniqueKey'] = [
            'keys' => ['array'], 'included' => ['array'], 'name' => ['?Identifier'],
            'timing' => [$key . 'KeyTiming'], 'nullsNotDistinct' => ['bool'],
        ];
        $methods[$key . 'UniqueKey'] = ['__construct', 'toString'];
        array_push($internal, $key . 'KeyConstraintLowering', $key . 'PrimaryKeyName', $key . 'UniqueKeyName', $key . 'TableKeySet');
        array_push($pureCollaborators, $key . 'PrimaryKeyName', $key . 'UniqueKeyName', $key . 'TableKeySet');
        $btree = 'Schema\\Index\\Btree\\';
        $btreeCreate = $btree . 'CreateIndex';
        $roots[] = $btreeCreate;
        array_push($internal, $btree . 'CreateIndexLowering', $btree . 'IndexTableLowering', $btree . 'IndexOperands',
            $btree . 'IndexPredicateLowering', $btree . 'IndexCondition');
        $pureCollaborators[] = $btree . 'IndexCondition';
        $properties[$btreeCreate] = [
            'keys' => ['array'], 'included' => ['array'], 'table' => ['TableSource'], 'name' => ['Identifier'],
            'presence' => [$btree . 'IndexPresence'], 'ifNotExists' => ['bool'], 'unique' => ['bool'],
            'nullsNotDistinct' => ['bool'], 'concurrently' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
            'predicate' => [$btree . 'IndexBooleanColumn', $btree . 'IndexBooleanPredicate', $btree . 'IndexTruthTest', $btree . 'NullIndexPredicate', 'null'],
        ];
        $properties[$btree . 'IndexKey'] = ['slot' => ['int'], 'order' => [$btree . 'IndexOrder']];
        $properties[$btree . 'IncludedColumn'] = ['slot' => ['int']];
        $properties[$btree . 'NullIndexPredicate'] = ['slot' => ['int'], 'negated' => ['bool']];
        $properties[$btree . 'IndexBooleanColumn'] = ['slot' => ['int']];
        $properties[$btree . 'IndexTruthTest'] = ['slot' => ['int'], 'operator' => ['Predicate\\TruthOperator']];
        $properties[$btree . 'IndexBooleanPredicate'] = [
            'operator' => ['Predicate\\LogicalOperator'],
            'left' => [$btree . 'IndexBooleanColumn', $btree . 'IndexBooleanPredicate', $btree . 'IndexTruthTest', $btree . 'NullIndexPredicate'],
            'right' => [$btree . 'IndexBooleanColumn', $btree . 'IndexBooleanPredicate', $btree . 'IndexTruthTest', $btree . 'NullIndexPredicate', 'null'],
        ];
        $properties[$btree . 'IndexOrder'] = $methods[$btree . 'IndexOrder'] = [];
        $properties[$btree . 'IndexPresence'] = $methods[$btree . 'IndexPresence'] = [];
        $enumCases[$btree . 'IndexPresence'] = ['Vacant' => 'vacant', 'Occupied' => 'occupied'];
        $enumCases[$btree . 'IndexOrder'] = ['AscendingNullsFirst' => 'ASC NULLS FIRST', 'AscendingNullsLast' => 'ASC NULLS LAST',
            'DescendingNullsFirst' => 'DESC NULLS FIRST', 'DescendingNullsLast' => 'DESC NULLS LAST'];
        $methods[$btreeCreate] = ['__construct', 'profileId', 'toString'];
        $methods[$btree . 'IndexKey'] = $methods[$btree . 'IncludedColumn'] = ['__construct'];
        $methods[$btree . 'NullIndexPredicate'] = $methods[$btree . 'IndexBooleanPredicate'] = $methods[$btree . 'IndexTruthTest'] = ['__construct'];
        $methods[$btree . 'IndexBooleanColumn'] = ['__construct'];
        $schemaRemoval = 'Schema\\Namespace\\DropSchema';
        $schemaTarget = 'Schema\\Namespace\\SchemaDropTarget';
        $roots[] = $schemaRemoval;
        $internal[] = 'Schema\\Namespace\\DropSchemaLowering';
        $properties[$schemaRemoval] = ['targets' => ['array'], 'ifExists' => ['bool'], 'cascade' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$schemaTarget] = ['name' => ['Identifier'], 'present' => ['bool']];
        $methods[$schemaRemoval] = ['__construct', 'profileId', 'toString'];
        $methods[$schemaTarget] = ['__construct'];
        $schemaCreation = 'Schema\\Namespace\\CreateSchema';
        $schemaSession = 'Schema\\Namespace\\SchemaSessionRole';
        $roots[] = $schemaCreation;
        array_push($internal, 'Schema\\Namespace\\CreateSchemaLowering', 'Schema\\Namespace\\SchemaTableLowering',
            'Schema\\Namespace\\SchemaSequenceLowering', 'Schema\\Namespace\\SchemaRelationOrder',
            'Schema\\Namespace\\View\\SchemaViewLowering', 'Schema\\Namespace\\View\\SchemaViewOrder', 'Schema\\Namespace\\View\\SchemaViewInputs',
            'Schema\\Namespace\\View\\SchemaViewFields', 'Schema\\Namespace\\View\\SchemaViewReplacement',
            'Schema\\Namespace\\Index\\SchemaIndexLowering', 'Schema\\Namespace\\Index\\SchemaIndexOrder',
            'Schema\\Namespace\\Access\\SchemaGrantLowering', 'Schema\\Namespace\\Access\\SchemaGrantOrder');
        array_push($pureCollaborators, 'Schema\\Namespace\\SchemaRelationOrder', 'Schema\\Namespace\\View\\SchemaViewOrder', 'Schema\\Namespace\\Index\\SchemaIndexOrder', 'Schema\\Namespace\\Access\\SchemaGrantOrder', 'Schema\\Namespace\\View\\SchemaViewInputs', 'Schema\\Namespace\\View\\SchemaViewFields', 'Schema\\Namespace\\View\\SchemaViewReplacement');
        $properties[$schemaCreation] = [
            'name' => ['?Identifier'], 'tables' => ['array'], 'sequences' => ['array'], 'views' => ['array'], 'indexes' => ['array'], 'grants' => ['array'],
            'owner' => ['Identifier', $schemaSession], 'ifNotExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
        ];
        $methods[$schemaCreation] = ['__construct', 'profileId', 'toString'];
        $properties[$schemaSession] = $methods[$schemaSession] = [];
        $enumCases[$schemaSession] = ['Effective' => 'CURRENT_USER', 'Session' => 'SESSION_USER'];
        $schemaRename = 'Schema\\Namespace\\RenameSchema';
        $schemaOwnership = 'Schema\\Namespace\\ChangeSchemaOwner';
        array_push($roots, $schemaRename, $schemaOwnership);
        array_push($internal, 'Schema\\Namespace\\SchemaAlterLowering', 'Schema\\Namespace\\SchemaOwnerLowering', 'Schema\\Namespace\\SchemaTargetLowering');
        $properties[$schemaRename] = ['schema' => ['Identifier'], 'destination' => ['Identifier'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$schemaOwnership] = ['schema' => ['Identifier'], 'owner' => ['Identifier', $schemaSession],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$schemaRename] = $methods[$schemaOwnership] = ['__construct', 'profileId', 'toString'];
        $sequence = 'Schema\\Sequence\\';
        $sequenceCreate = $sequence . 'CreateSequence';
        $sequenceName = $sequence . 'SequenceName';
        $sequenceParameters = $sequence . 'SequenceParameters';
        $sequencePersistence = $sequence . 'SequencePersistence';
        $sequenceOwner = $sequence . 'Ownership\\SequenceOwner';
        $sequenceOwnerKind = $sequence . 'Ownership\\SequenceOwnerKind';
        $roots[] = $sequenceCreate;
        array_push($internal, $sequence . 'CreateSequenceLowering', $sequence . 'SequenceOptionLowering',
            $sequence . 'SequenceOptionsLowering', $sequence . 'SequenceIntegerOrder', $sequence . 'Ownership\\SequenceOwnerLowering');
        $pureCollaborators[] = $sequence . 'SequenceIntegerOrder';
        $properties[$sequenceCreate] = ['name' => [$sequenceName], 'parameters' => [$sequenceParameters],
            'persistence' => [$sequencePersistence], 'ifNotExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'owner' => ['?' . $sequenceOwner]];
        $properties[$sequenceName] = ['schema' => ['Identifier'], 'name' => ['Identifier']];
        $properties[$sequenceParameters] = ['storage' => ['Schema\\IntegerStorage'], 'cycle' => ['bool']];
        foreach (['increment', 'minimum', 'maximum', 'start', 'initial', 'cache'] as $operand) {
            $properties[$sequenceParameters][$operand] = ['Expression\\WideIntegerLiteral'];
        }
        $properties[$sequencePersistence] = [];
        $enumCases[$sequencePersistence] = ['Permanent' => '', 'Unlogged' => 'UNLOGGED',
            'Temporary' => 'TEMPORARY', 'GlobalTemporary' => 'GLOBAL TEMPORARY'];
        $methods[$sequenceCreate] = ['__construct', 'profileId', 'toString'];
        $methods[$sequenceName] = ['__construct', 'quoted'];
        $methods[$sequenceParameters] = ['__construct'];
        $methods[$sequencePersistence] = ['temporary'];
        $properties[$sequenceOwner] = ['schema' => ['Identifier'], 'relation' => ['Identifier'], 'column' => ['Identifier'],
            'kind' => [$sequenceOwnerKind]];
        $methods[$sequenceOwner] = ['__construct', 'quoted'];
        $properties[$sequenceOwnerKind] = $methods[$sequenceOwnerKind] = [];
        $enumCases[$sequenceOwnerKind] = ['BaseTable' => 'table', 'ForeignTable' => 'foreign', 'View' => 'view', 'PartitionedTable' => 'partitioned'];
        $sequenceDrop = $sequence . 'DropSequence';
        $sequenceDropTarget = $sequence . 'SequenceSelector';
        $sequenceDropPresence = $sequence . 'SequencePresence';
        $roots[] = $sequenceDrop;
        array_push($internal, $sequence . 'DropSequenceLowering', $sequence . 'SequenceTargetLowering');
        $properties[$sequenceDrop] = ['targets' => ['array'], 'ifExists' => ['bool'], 'cascade' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$sequenceDropTarget] = ['name' => ['Identifier'], 'schema' => ['?Identifier'], 'presence' => [$sequenceDropPresence]];
        $properties[$sequenceDropPresence] = $methods[$sequenceDropPresence] = [];
        $enumCases[$sequenceDropPresence] = ['Existing' => 'existing', 'MissingSequence' => 'missing-sequence', 'MissingNamespace' => 'missing-namespace'];
        $methods[$sequenceDrop] = ['__construct', 'profileId', 'toString'];
        $methods[$sequenceDropTarget] = ['__construct', 'quoted', 'checkProfile'];
        $sequenceRename = $sequence . 'Alter\\RenameSequence';
        $roots[] = $sequenceRename;
        $internal[] = $sequence . 'Alter\\RenameSequenceLowering';
        $properties[$sequenceRename] = ['source' => [$sequenceDropTarget], 'destination' => ['Identifier'], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$sequenceRename] = ['__construct', 'profileId', 'toString'];
        $sequenceMove = $sequence . 'Alter\\MoveSequence';
        $roots[] = $sequenceMove;
        $internal[] = $sequence . 'Alter\\MoveSequenceLowering';
        $properties[$sequenceMove] = ['source' => [$sequenceDropTarget], 'destination' => ['?Identifier'], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$sequenceMove] = ['__construct', 'profileId', 'toString'];
        $reconfigure = $sequence . 'Alter\\ReconfigureSequence';
        $sequenceChanges = $sequence . 'Alter\\SequenceChanges';
        $sequenceDefault = $sequence . 'Alter\\SequenceResetOperand';
        $clearSequenceOwner = $sequence . 'Alter\\ClearSequenceOwner';
        $sequenceChangeRules = $sequence . 'Alter\\SequenceChangeRules';
        $roots[] = $reconfigure;
        array_push($internal, $sequence . 'Alter\\ReconfigureSequenceLowering', $sequence . 'Alter\\SequenceChangesLowering', $sequenceChangeRules);
        $pureCollaborators[] = $sequenceChangeRules;
        $properties[$reconfigure] = ['source' => [$sequenceDropTarget], 'changes' => ['?' . $sequenceChanges], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$reconfigure] = ['__construct', 'profileId', 'toString'];
        $numericChoice = ['Expression\\WideIntegerLiteral', $sequenceDefault, 'null'];
        $properties[$sequenceChanges] = [
            'storage' => ['?Schema\\IntegerStorage'], 'increment' => ['?Expression\\WideIntegerLiteral'],
            'minimum' => $numericChoice, 'maximum' => $numericChoice, 'start' => ['?Expression\\WideIntegerLiteral'],
            'restart' => $numericChoice, 'cache' => ['?Expression\\WideIntegerLiteral'], 'cycle' => ['?bool'],
            'owner' => [$clearSequenceOwner, $sequenceOwner, 'null'],
        ];
        $methods[$sequenceChanges] = ['__construct', 'toString'];
        $properties[$sequenceDefault] = $methods[$sequenceDefault] = [];
        $properties[$clearSequenceOwner] = $methods[$clearSequenceOwner] = [];
        $enumCases[$sequenceDefault] = ['Native' => 'native-default'];
        $enumCases[$clearSequenceOwner] = ['Remove' => 'remove'];
        $setLogging = $sequence . 'Alter\\SetSequenceLogging';
        $loggingMode = $sequence . 'Alter\\SequenceLogging';
        $roots[] = $setLogging;
        $internal[] = $sequence . 'Alter\\SetSequenceLoggingLowering';
        $properties[$setLogging] = ['source' => [$sequenceDropTarget], 'modes' => ['array'], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$setLogging] = ['__construct', 'profileId', 'toString'];
        $properties[$loggingMode] = $methods[$loggingMode] = [];
        $enumCases[$loggingMode] = ['Logged' => 'LOGGED', 'Unlogged' => 'UNLOGGED'];
        $loggingPrefix = $sequence . 'Alter\\Logging\\SequenceLoggingPrefix';
        $authorization = $sequence . 'Authorization\\AlterSequenceAuthorization';
        $authorizationAction = $sequence . 'Authorization\\SequenceAuthorizationAction';
        $principal = $sequence . 'Authorization\\SequencePrincipal';
        $roots[] = $authorization;
        array_push($internal, $loggingPrefix, $sequence . 'Alter\\Logging\\SequenceLoggingModeLowering',
            $sequence . 'Authorization\\SequenceActionsLowering', $sequence . 'Authorization\\SequenceAuthorizationLowering');
        $pureCollaborators[] = $loggingPrefix;
        $properties[$authorization] = ['source' => [$sequenceDropTarget], 'actions' => ['array'], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$authorization] = ['__construct', 'profileId', 'toString'];
        $properties[$authorizationAction] = ['value' => [$loggingMode, $principal]];
        $methods[$authorizationAction] = ['__construct', 'toString'];
        $properties[$principal] = ['owner' => ['Identifier', 'Schema\\Namespace\\SchemaSessionRole']];
        $methods[$principal] = ['__construct', 'checkRelease', 'toString', 'reference'];
        $access = $sequence . 'Access\\';
        $grantAccess = $access . 'GrantSequenceAccess';
        $revokeAccess = $access . 'RevokeSequenceAccess';
        $accessOperands = $access . 'SequenceAccess';
        $grantee = $access . 'SequenceGrantee';
        $universal = $access . 'UniversalGrantee';
        $right = $access . 'SequenceRight';
        $grantDependency = $access . 'SequenceGrantDependency';
        $namedSequences = $access . 'NamedSequences';
        $schemaSequences = $access . 'SequencesInSchemas';
        array_push($roots, $grantAccess, $revokeAccess);
        array_push($internal, $access . 'SequenceAccessLowering', $access . 'SequenceAccessTargetLowering');
        $properties[$grantAccess] = ['access' => [$accessOperands], 'grantOption' => ['bool']];
        $properties[$revokeAccess] = ['access' => [$accessOperands], 'grantOptionOnly' => ['bool'], 'dependency' => [$grantDependency]];
        $methods[$grantAccess] = $methods[$revokeAccess] = ['__construct', 'profileId', 'toString'];
        $properties[$accessOperands] = ['target' => [$namedSequences, $schemaSequences], 'rights' => ['array'], 'grantees' => ['array'],
            'grantor' => ['?' . $principal], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$accessOperands] = ['__construct', 'profileId', 'hasEffectiveRights', 'objectSql', 'recipientsSql', 'grantorSql'];
        $properties[$namedSequences] = ['sequences' => ['array']];
        $methods[$namedSequences] = ['__construct', 'checkRelease', 'toString'];
        $properties[$schemaSequences] = ['schemas' => ['array']];
        $methods[$schemaSequences] = ['__construct', 'toString'];
        $properties[$grantee] = ['role' => [$universal, $principal]];
        $methods[$grantee] = ['__construct', 'checkRelease', 'toString'];
        $properties[$universal] = $methods[$universal] = $properties[$right] = $methods[$right] = $properties[$grantDependency] = $methods[$grantDependency] = [];
        $enumCases[$universal] = ['Everyone' => 'PUBLIC'];
        $enumCases[$right] = ['Select' => 'SELECT', 'Update' => 'UPDATE', 'Usage' => 'USAGE', 'LegacyRule' => 'RULE'];
        $enumCases[$grantDependency] = ['Restrict' => 'RESTRICT', 'Cascade' => 'CASCADE'];
        $defaults = $sequence . 'Defaults\\';
        $grantDefaults = $defaults . 'GrantSequenceDefaults';
        $revokeDefaults = $defaults . 'RevokeSequenceDefaults';
        $defaultPolicy = $defaults . 'SequenceDefaultPolicy';
        array_push($roots, $grantDefaults, $revokeDefaults);
        array_push($internal, $access . 'SequencePrivilegeLowering', $defaults . 'SequenceDefaultLowering', $defaults . 'SequenceDefaultScopesLowering');
        $properties[$grantDefaults] = ['policy' => [$defaultPolicy], 'grantOption' => ['bool']];
        $properties[$revokeDefaults] = ['policy' => [$defaultPolicy], 'grantOptionOnly' => ['bool'], 'dependency' => [$grantDependency]];
        $methods[$grantDefaults] = $methods[$revokeDefaults] = ['__construct', 'profileId', 'toString'];
        $properties[$defaultPolicy] = ['creators' => ['array'], 'schemas' => ['array'], 'rights' => ['array'], 'grantees' => ['array'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$defaultPolicy] = ['__construct', 'profileId', 'scopeSql', 'objectSql', 'recipientsSql'];
        $indexRemoval = 'Schema\\Index\\DropIndex';
        $indexReference = 'Schema\\Index\\IndexReference';
        $indexMissing = 'Schema\\Index\\MissingIndex';
        $indexMode = 'Schema\\Index\\IndexDropMode';
        $roots[] = $indexRemoval;
        array_push($internal, 'Schema\\Index\\DropIndexLowering', 'Schema\\Index\\IndexTargetLowering', 'Schema\\Index\\ReindexIndexLowering');
        $reindex = 'Schema\\Index\\ReindexIndex';
        $rebuildMode = 'Schema\\Index\\IndexRebuildMode';
        $constraintIndex = 'Schema\\Index\\ConstraintIndexReference';
        $exclusionIndex = 'Schema\\Index\\ExclusionIndexReference';
        $partitionIndex = 'Schema\\Index\\Partition\\PartitionIndexReference';
        $roots[] = $reindex;
        $properties[$reindex] = ['target' => [$constraintIndex, $exclusionIndex, $indexReference, $partitionIndex], 'mode' => [$rebuildMode], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'verbose' => ['bool']];
        $properties[$constraintIndex] = ['schema' => ['Identifier'], 'index' => ['Identifier'], 'table' => ['Identifier']];
        $properties[$exclusionIndex] = $properties[$constraintIndex];
        $methods[$constraintIndex] = $methods[$exclusionIndex] = ['__construct', 'quoted'];
        $methods[$reindex] = ['__construct', 'profileId', 'toString'];
        $reindexSchema = 'Schema\\Index\\Scope\\ReindexSchema';
        $roots[] = $reindexSchema;
        $internal[] = 'Schema\\Index\\Scope\\ReindexSchemaLowering';
        $properties[$reindexSchema] = ['schema' => ['Identifier'], 'mode' => [$rebuildMode], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'verbose' => ['bool']];
        $methods[$reindexSchema] = ['__construct', 'profileId', 'toString'];
        $reindexDatabase = 'Schema\\Index\\Scope\\ReindexDatabase';
        $reindexSystem = 'Schema\\Index\\Scope\\ReindexSystemCatalogs';
        array_push($roots, $reindexDatabase, $reindexSystem);
        $internal[] = 'Schema\\Index\\Scope\\ReindexDatabaseLowering';
        $properties[$reindexSystem] = ['database' => ['Identifier'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'verbose' => ['bool']];
        $properties[$reindexDatabase] = $properties[$reindexSystem] + ['mode' => [$rebuildMode]];
        $methods[$reindexDatabase] = $methods[$reindexSystem] = ['__construct', 'profileId', 'toString'];
        $reindexTable = 'Schema\\Index\\ReindexTable';
        $reindexOwner = 'Schema\\Index\\ReindexTableTarget';
        $reindexKind = 'Schema\\Index\\ReindexTableKind';
        $roots[] = $reindexTable;
        $internal[] = 'Schema\\Index\\ReindexTableLowering';
        $properties[$reindexTable] = ['target' => [$reindexOwner], 'mode' => [$rebuildMode], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'verbose' => ['bool']];
        $properties[$reindexOwner] = ['schema' => ['Identifier'], 'name' => ['Identifier'], 'kind' => [$reindexKind]];
        $methods[$reindexTable] = ['__construct', 'profileId', 'toString'];
        $methods[$reindexOwner] = ['__construct', 'quoted'];
        $properties[$reindexKind] = $methods[$reindexKind] = [];
        $enumCases[$reindexKind] = ['BaseTable' => 'base-table', 'MaterializedView' => 'materialized-view'];
        $partitionRebuild = 'Schema\\Index\\Partition\\ReindexPartitionTable';
        $partitionRebuildKind = 'Schema\\Index\\Partition\\PartitionRebuildKind';
        $properties[$partitionIndex] = ['schema' => ['Identifier'], 'index' => ['Identifier'], 'table' => ['Identifier'], 'kind' => [$partitionRebuildKind]];
        $methods[$partitionIndex] = ['__construct', 'quoted'];
        $roots[] = $partitionRebuild;
        $properties[$partitionRebuild] = ['target' => ['Schema\\TableName'], 'kind' => [$partitionRebuildKind], 'mode' => [$rebuildMode], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'verbose' => ['bool']];
        $methods[$partitionRebuild] = ['__construct', 'profileId', 'toString'];
        $rebuildPlacement = 'Schema\\Index\\Placement\\IndexTablespace';
        $properties[$rebuildPlacement] = ['name' => ['Identifier']];
        $methods[$rebuildPlacement] = ['__construct', 'prefix'];
        $internal[] = 'Schema\\Index\\Placement\\IndexTablespaceLowering';
        foreach ([$reindex, $reindexTable, $partitionRebuild, $reindexSchema, $reindexDatabase, $reindexSystem] as $rebuildRoot) {
            $properties[$rebuildRoot]['tablespace'] = ['?' . $rebuildPlacement];
        }
        $properties[$partitionRebuildKind] = $methods[$partitionRebuildKind] = [];
        $enumCases[$partitionRebuildKind] = ['Branch' => 'branch', 'Leaf' => 'leaf'];
        $properties[$rebuildMode] = $methods[$rebuildMode] = [];
        $enumCases[$rebuildMode] = ['Standard' => 'standard', 'Concurrent' => 'concurrently'];
        $properties[$indexRemoval] = ['targets' => ['array'], 'ifExists' => ['bool'], 'cascade' => ['bool'],
            'mode' => [$indexMode], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$indexReference] = ['schema' => ['Identifier'], 'index' => ['Identifier'], 'table' => ['Identifier']];
        $properties[$indexMissing] = ['index' => ['Identifier'], 'schema' => ['?Identifier'], 'missingSchema' => ['bool']];
        $properties[$indexMode] = $methods[$indexMode] = [];
        $enumCases[$indexMode] = ['Ordinary' => 'ordinary', 'Concurrently' => 'concurrently'];
        $methods[$indexRemoval] = ['__construct', 'profileId', 'toString'];
        $methods[$indexReference] = $methods[$indexMissing] = ['__construct', 'quoted'];
        $viewRemoval = 'Schema\\View\\DropView';
        $viewName = 'Schema\\View\\ViewName';
        $valuesQuery = 'Rows\\Values\\ValuesQuery';
        $valuesRow = 'Rows\\Values\\ValuesRow';
        $valuesCell = 'Rows\\Values\\ValuesCell';
        $roots[] = $valuesQuery;
        array_push($internal, 'Rows\\Values\\ValuesLowering', 'Rows\\Values\\ColumnUnification', 'Schema\\DropRelationLowering');
        $pureCollaborators[] = 'Rows\\Values\\ColumnUnification';
        $properties[$valuesQuery] = ['rows' => ['array'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$valuesRow] = ['cells' => ['array']];
        $properties[$valuesCell] = ['value' => ['BooleanLiteral', 'Expression\\BuiltinCast', 'Expression\\TextLiteral', 'Expression\\WideIntegerLiteral', 'IntegerLiteral', 'NullLiteral']];
        $methods[$valuesQuery] = ['__construct', 'columnKind', 'profileId', 'toString'];
        $methods[$valuesRow] = ['__construct', 'toString'];
        $methods[$valuesCell] = ['__construct', 'kind', 'toString'];
        $viewCreate = 'Schema\\View\\CreateView';
        $viewCheck = 'Schema\\View\\ViewCheckOption';
        $roots[] = $viewCreate;
        $createMaterialized = 'Schema\\View\\CreateMaterializedView';
        $refreshMaterialized = 'Schema\\View\\RefreshMaterializedView';
        array_push($roots, $createMaterialized, $refreshMaterialized);
        array_push($internal, 'Schema\\RelationDeclarationLowering', 'Schema\\View\\CreateMaterializedViewLowering',
            'Schema\\View\\RefreshMaterializedViewLowering', 'Schema\\View\\ViewHeading', 'Schema\\DefinitionQueryLowering');
        $pureCollaborators[] = 'Schema\\View\\ViewHeading';
        $createAs = 'Schema\\Query\\CreateTableAs';
        $createAsBounds = 'Schema\\Query\\CreateAsBounds';
        $roots[] = $createAs;
        $truncate = 'Schema\\Truncate\\TruncateTable';
        $truncateTarget = 'Schema\\Truncate\\TruncateTarget';
        $roots[] = $truncate;
        $internal[] = 'Schema\\Truncate\\TruncateLowering';
        $properties[$truncate] = ['targets' => ['array'], 'restartIdentity' => ['bool'], 'cascade' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$truncateTarget] = ['table' => ['Schema\\TableName'], 'only' => ['bool']];
        $methods[$truncate] = ['__construct', 'profileId', 'toString'];
        $methods[$truncateTarget] = ['__construct', 'toString'];
        $renameTable = 'Schema\\Alter\\RenameTable';
        $renameColumn = 'Schema\\Alter\\RenameColumn';
        array_push($roots, $renameTable, $renameColumn);
        array_push($internal, 'Schema\\Alter\\RenameLowering', 'Schema\\Alter\\RenameTargetLowering', 'Schema\\Alter\\RelationChangeLowering');
        $properties[$renameTable] = ['target' => ['Schema\\TableName'], 'destination' => ['Identifier'], 'ifExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$renameColumn] = [...$properties[$renameTable], 'column' => ['Identifier'], 'only' => ['bool']];
        $methods[$renameTable] = $methods[$renameColumn] = ['__construct', 'profileId', 'toString'];
        array_push($internal, $createAsBounds, 'Schema\\Query\\CreateTableAsLowering');
        $pureCollaborators[] = $createAsBounds;
        $properties[$createAs] = ['target' => ['Schema\\TableName'], 'query' => [$valuesQuery, 'Select'],
            'columns' => ['array'], 'unlogged' => ['bool'], 'ifNotExists' => ['bool'], 'withData' => ['bool']];
        $methods[$createAs] = ['__construct', 'profileId', 'toString'];
        $properties[$createMaterialized] = ['name' => [$viewName], 'query' => [$valuesQuery, 'Select'],
            'columns' => ['array'], 'ifNotExists' => ['bool'], 'withData' => ['bool']];
        $properties[$refreshMaterialized] = ['name' => [$viewName], 'withData' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$createMaterialized] = $methods[$refreshMaterialized] = ['__construct', 'profileId', 'toString'];
        array_push($internal, 'Schema\\View\\CreateViewLowering', 'Schema\\View\\ViewDefinitionBounds', 'Schema\\View\\ViewOptionsLowering');
        $pureCollaborators[] = 'Schema\\View\\ViewDefinitionBounds';
        $properties[$viewCreate] = ['name' => [$viewName], 'query' => [$valuesQuery, 'Select'], 'columns' => ['array'],
            'replace' => ['bool'], 'securityBarrier' => ['?bool'], 'securityInvoker' => ['?bool'], 'checkOption' => [$viewCheck]];
        $properties[$viewCheck] = $methods[$viewCheck] = [];
        $enumCases[$viewCheck] = ['None' => '', 'Local' => 'LOCAL', 'Cascaded' => 'CASCADED'];
        $methods[$viewCreate] = ['__construct', 'profileId', 'toString'];
        $viewKind = 'Schema\\View\\ViewRemovalKind';
        $roots[] = $viewRemoval;
        $internal[] = 'Schema\\View\\DropViewLowering';
        $properties[$viewRemoval] = ['kind' => [$viewKind], 'targets' => ['array'], 'ifExists' => ['bool'],
            'cascade' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$viewName] = ['schema' => ['Identifier'], 'view' => ['Identifier']];
        $properties[$viewKind] = $methods[$viewKind] = [];
        $enumCases[$viewKind] = ['Ordinary' => 'VIEW', 'Materialized' => 'MATERIALIZED VIEW'];
        $methods[$viewRemoval] = ['__construct', 'profileId', 'toString'];
        $methods[$viewName] = ['__construct', 'quoted'];
    } else {
        $renameTable = 'Schema\\Alter\\RenameTable';
        $tableRename = 'Schema\\Alter\\TableRename';
        $tableName = 'Schema\\TableName';
        $roots[] = $renameTable;
        array_push($internal, 'Schema\\Alter\\TableRenameLowering', 'Schema\\Alter\\RelationChangeLowering');
        $properties[$renameTable] = ['moves' => ['array'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$tableRename] = ['from' => [$tableName], 'to' => [$tableName], 'origin' => [$tableName]];
        $truncate = 'Schema\\Truncate\\TruncateTable';
        $roots[] = $truncate;
        $internal[] = 'Schema\\Truncate\\TruncateLowering';
        $internal[] = 'Schema\\Truncate\\TruncationDependents';
        $pureCollaborators[] = 'Schema\\Truncate\\TruncationDependents';
        $index = 'Schema\\Index\\CreateIndex';
        $part = 'Schema\\Index\\IndexColumn';
        $policy = 'Schema\\Index\\Policy\\IndexChangePolicy';
        $algorithm = 'Schema\\Index\\Policy\\IndexAlgorithm';
        $lock = 'Schema\\Index\\Policy\\IndexLock';
        $properties[$policy] = ['algorithm' => [$algorithm], 'lock' => [$lock]];
        $properties[$algorithm] = $properties[$lock] = [];
        $methods[$policy] = ['__construct', 'supports', 'sql'];
        $methods[$algorithm] = $methods[$lock] = [];
        $enumCases[$algorithm] = ['Default' => 'DEFAULT', 'InPlace' => 'INPLACE', 'Copy' => 'COPY'];
        $enumCases[$lock] = ['Default' => 'DEFAULT', 'None' => 'NONE', 'Shared' => 'SHARED', 'Exclusive' => 'EXCLUSIVE'];
        array_push($internal, 'Schema\\Index\\IndexNamespace', 'Schema\\Index\\Policy\\IndexPolicyLowering');
        $pureCollaborators[] = 'Schema\\Index\\IndexNamespace';
        $roots[] = $index;
        array_push($internal, 'Schema\\Index\\CreateIndexLowering', 'Schema\\Index\\IndexOptionsLowering',
            'Schema\\Index\\IndexTableLowering', 'Schema\\Index\\IndexPartsLowering');
        $properties[$index] = ['target' => ['TableSource'], 'name' => ['Identifier'], 'columns' => ['array'],
            'unique' => ['bool'], 'visible' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
            'ownerHasIdentityKey' => ['bool'], 'priorKeys' => ['array'], 'policy' => [$policy]];
        $properties[$part] = ['slot' => ['int'], 'descending' => ['bool']];
        $methods[$index] = ['__construct', 'profileId', 'toString'];
        $methods[$part] = ['__construct'];
        $dropIndex = 'Schema\\Index\\DropIndex';
        $indexIdentity = 'Schema\\Index\\IndexIdentity';
        $indexRole = 'Schema\\Index\\IndexRole';
        $indexAutoIncrement = 'Schema\\Index\\IndexAutoIncrement';
        $internal[] = 'Schema\\Index\\IndexAutoIncrementLowering';
        $properties[$indexAutoIncrement] = ['field' => ['Identifier'], 'alternates' => ['array']];
        $methods[$indexAutoIncrement] = ['__construct'];
        $indexForeignKey = 'Schema\\Index\\Dependency\\IndexForeignKeyRequirement';
        $dependencyClosure = 'Schema\\Index\\Dependency\\IndexDependencyClosure';
        $properties[$indexForeignKey] = ['owner' => ['Schema\\Identity\\TableIdentity'], 'keys' => ['array']];
        $methods[$indexForeignKey] = ['__construct'];
        array_push($internal, $dependencyClosure, 'Schema\\Index\\Dependency\\IndexDependencyLowering',
            'Schema\\Index\\Dependency\\IndexDependencyKeys');
        $pureCollaborators[] = $dependencyClosure;
        $dropIndexes = 'Schema\\Index\\DropIndexes';
        $batchRemoval = 'Schema\\Index\\Batch\\IndexRemovalSet';
        $backingRemoval = 'Schema\\Index\\Batch\\AutoIncrementRemovalSet';
        array_push($roots, $dropIndex, $dropIndexes);
        array_push($internal, 'Schema\\Index\\DropIndexLowering', 'Schema\\Alter\\AlterIndexRemovalLowering', $batchRemoval, $backingRemoval);
        array_push($pureCollaborators, $batchRemoval, $backingRemoval);
        $properties[$dropIndexes] = ['targets' => ['array'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
            'policy' => [$policy], 'ignore' => ['bool']];
        $methods[$dropIndexes] = ['__construct', 'profileId', 'toString'];
        $indexRename = 'Schema\\Index\\Rename\\IndexRename';
        $renameIndexes = 'Schema\\Index\\Rename\\RenameIndexes';
        $renameNamespace = 'Schema\\Index\\Rename\\IndexRenameNamespace';
        $roots[] = $renameIndexes;
        array_push($internal, 'Schema\\Alter\\AlterIndexRenameLowering', $renameNamespace);
        $pureCollaborators[] = $renameNamespace;
        $properties[$indexRename] = ['source' => ['Identifier'], 'destination' => ['Identifier']];
        $properties[$renameIndexes] = ['owner' => ['Schema\\Identity\\TableIdentity'], 'moves' => ['array'], 'unmoved' => ['array'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'policy' => [$policy]];
        $methods[$indexRename] = ['__construct', 'toString'];
        $methods[$renameIndexes] = ['__construct', 'profileId', 'toString'];
        $visibilityRoot = 'Schema\\Index\\Visibility\\SetIndexVisibility';
        $visibilityEdit = 'Schema\\Index\\Visibility\\IndexVisibilityEdit';
        $secondaryIndex = 'Schema\\Index\\Visibility\\SecondaryIndexKind';
        $roots[] = $visibilityRoot;
        $internal[] = 'Schema\\Index\\Visibility\\IndexVisibilityLowering';
        $properties[$visibilityRoot] = ['owner' => ['Schema\\Identity\\TableIdentity'], 'edits' => ['array'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'policy' => [$policy]];
        $methods[$visibilityRoot] = ['__construct', 'profileId', 'toString'];
        $properties[$visibilityEdit] = ['index' => ['Identifier'], 'kind' => [$secondaryIndex], 'visible' => ['bool']];
        $methods[$visibilityEdit] = ['__construct', 'toString'];
        $properties[$secondaryIndex] = $methods[$secondaryIndex] = [];
        $enumCases[$secondaryIndex] = ['Ordinary' => 'ordinary', 'Unique' => 'unique', 'FullText' => 'fulltext', 'Spatial' => 'spatial'];
        $mixedAlter = 'Schema\\Index\\Change\\AlterIndexes';
        $indexAlteration = 'Schema\\Index\\Change\\IndexAlteration';
        $mixedChanges = 'Schema\\Index\\Change\\MixedIndexChanges';
        $mixedNamespace = 'Schema\\Index\\Change\\MixedIndexNamespace';
        $roots[] = $mixedAlter;
        array_push($internal, 'Schema\\Alter\\AlterMixedIndexesLowering', $mixedChanges, $mixedNamespace);
        array_push($pureCollaborators, $mixedChanges, $mixedNamespace);
        $properties[$indexAlteration] = ['operation' => [$indexIdentity, $indexRename]];
        $properties[$mixedAlter] = ['owner' => ['Schema\\Identity\\TableIdentity'], 'actions' => ['array'], 'unmoved' => ['array'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'policy' => [$policy]];
        $methods[$indexAlteration] = ['__construct', 'toString'];
        $methods[$mixedAlter] = ['__construct', 'profileId', 'toString'];
        $properties[$dropIndex] = ['target' => [$indexIdentity], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'], 'policy' => [$policy], 'ignore' => ['bool']];
        $properties[$indexIdentity] = ['owner' => ['Schema\\Identity\\TableIdentity'], 'name' => ['Identifier'], 'role' => [$indexRole],
            'autoIncrement' => ['?' . $indexAutoIncrement]];
        $properties[$indexRole] = [];
        $methods[$dropIndex] = ['__construct', 'profileId', 'toString'];
        $methods[$indexIdentity] = ['__construct'];
        $methods[$indexRole] = ['clustered'];
        $enumCases[$indexRole] = ['Primary' => 'primary', 'ClusteredUnique' => 'clustered-unique', 'Unique' => 'unique', 'Ordinary' => 'ordinary'];
        $identity = 'Schema\\Identity\\TableIdentity';
        $properties[$identity] = ['name' => [$tableName], 'kind' => ['From\\ExternalRelationKind']];
        $methods[$identity] = ['__construct', 'quoted'];
        $dropReference = 'Schema\\DropDependency\\TableDropReference';
        $dropDependencies = 'Schema\\DropDependency\\DropTableDependencies';
        $properties[$dropReference] = ['child' => [$identity], 'parent' => [$identity]];
        $methods[$dropReference] = ['__construct'];
        array_push($internal, $dropDependencies, 'Schema\\DropDependency\\DropReferenceLowering');
        $pureCollaborators[] = $dropDependencies;
        $copy = 'Schema\\Like\\CreateTableLike';
        $roots[] = $copy;
        $internal[] = 'Schema\\Like\\CreateTableLikeLowering';
        $properties[$copy] = ['destination' => [$identity], 'source' => [$identity], 'ifNotExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$copy] = ['__construct', 'profileId', 'toString'];
        $createAs = 'Schema\\Query\\CreateTableAs';
        $roots[] = $createAs;
        array_push($internal, 'Schema\\Query\\CreateTableAsLowering', 'Schema\\Query\\CreateAsLayout', 'Schema\\Query\\CreateAsRecord');
        array_push($pureCollaborators, 'Schema\\Query\\CreateAsLayout', 'Schema\\Query\\CreateAsRecord');
        $properties[$createAs] = ['target' => [$identity], 'query' => ['Select'], 'ifNotExists' => ['bool'],
            'declaredColumns' => ['array']];
        $methods[$createAs] = ['__construct', 'profileId', 'toString'];
        $absent = 'Schema\\Identity\\AbsentDropTarget';
        $properties[$absent] = ['name' => [$tableName]];
        $methods[$absent] = ['__construct', 'quoted'];
        $properties[$truncate] = ['target' => [$identity], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$truncate] = ['__construct', 'profileId', 'toString'];
        $methods[$renameTable] = ['__construct', 'profileId', 'toString'];
        $methods[$tableRename] = ['__construct', 'toString'];
        $properties['Schema\\CreateTable']['defaultCollation'] = ['?From\\TextCollation'];
        $properties['Schema\\CreateTable']['primaryKey'] = ['?Schema\\Key\\PrimaryKey'];
        $properties['Schema\\CreateTable']['target'] = [$identity];
        $properties['Schema\\CharacterStorage'] = ['length' => ['int'], 'collation' => ['From\\TextCollation']];
        $properties['Schema\\Key\\PrimaryKey'] = ['terms' => ['array']];
        $properties['Schema\\Key\\PrimaryKeyColumn'] = ['slot' => ['int'], 'descending' => ['bool']];
        $methods['Schema\\CharacterStorage'] = ['__construct', 'toString'];
        $methods['Schema\\Key\\PrimaryKey'] = $methods['Schema\\Key\\PrimaryKeyColumn'] = ['__construct'];
        $properties['Schema\\TableColumn']['storage'] = ['Schema\\CharacterStorage', 'Schema\\IntegerStorage'];
        $properties['Schema\\TableColumn']['default'] = ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Predicate\\RangeIntegerLiteral', 'null'];
        $methods['Schema\\TableColumn'] = ['__construct', 'storageBytes', 'toString'];
        $methods['Schema\\IntegerStorage'] = ['bytes', 'accepts'];
        $enumCases['Schema\\IntegerStorage'] = ['Tiny' => 'TINYINT', 'Small' => 'SMALLINT', 'Medium' => 'MEDIUMINT', 'Standard' => 'INTEGER', 'Wide' => 'BIGINT'];
        array_push($internal, 'Schema\\CreateColumnStorageLowering', 'Schema\\LiteralDefaultLowering');
        array_push($internal, 'Schema\\Key\\CreatePrimaryKeyLowering', 'Schema\\CreateTableLayoutLowering', 'Schema\\Key\\PrimaryColumnPolicy', 'Schema\\Storage\\InnoDbRecord');
        $pureCollaborators[] = 'Schema\\Storage\\InnoDbRecord';
        $viewRemoval = 'Schema\\View\\DropView';
        $viewName = 'Schema\\View\\ViewName';
        $roots[] = $viewRemoval;
        $internal[] = 'Schema\\View\\DropViewLowering';
        $properties[$viewRemoval] = ['targets' => ['array'], 'ifExists' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $properties[$viewName] = ['schema' => ['Identifier'], 'view' => ['Identifier']];
        $methods[$viewRemoval] = ['__construct', 'profileId', 'toString'];
        $methods[$viewName] = ['__construct', 'quoted'];
        $viewCreate = 'Schema\\View\\CreateView';
        $viewColumn = 'Schema\\View\\ViewColumn';
        $viewAlgorithm = 'Schema\\View\\ViewAlgorithm';
        $viewSecurity = 'Schema\\View\\ViewSecurity';
        $viewPolicy = 'Schema\\View\\ViewCreationPolicy';
        $roots[] = $viewCreate;
        array_push($internal, 'Schema\\CreateRelationLowering', 'Schema\\View\\CreateViewLowering', 'Schema\\View\\ViewQueryBounds');
        $pureCollaborators[] = 'Schema\\View\\ViewQueryBounds';
        $properties[$viewCreate] = ['name' => [$viewName], 'query' => ['Select'], 'columns' => ['array'],
            'policy' => [$viewPolicy], 'algorithm' => [$viewAlgorithm], 'security' => [$viewSecurity]];
        $properties[$viewColumn] = ['name' => ['Identifier']];
        $methods[$viewCreate] = ['__construct', 'profileId', 'toString'];
        $methods[$viewColumn] = ['__construct'];
        $properties[$viewAlgorithm] = $properties[$viewSecurity] = $properties[$viewPolicy] = [];
        $methods[$viewAlgorithm] = $methods[$viewSecurity] = $methods[$viewPolicy] = [];
        $enumCases[$viewAlgorithm] = ['Undefined' => 'UNDEFINED', 'Merge' => 'MERGE', 'TempTable' => 'TEMPTABLE'];
        $enumCases[$viewSecurity] = ['Definer' => 'DEFINER', 'Invoker' => 'INVOKER'];
        $enumCases[$viewPolicy] = ['RequireNew' => 'require-new', 'Replace' => 'replace', 'Preserve' => 'preserve'];
    }
    array_push($internal, 'Schema\\CreateColumnLowering', 'Schema\\CreateTableLowering');
    $properties['Schema\\DropTable'] = [
        'targets' => ['array'], 'ifExists' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    if ($dialect === 'postgres') {
        $properties['Schema\\DropTable']['cascade'] = ['bool'];
    } else {
        $properties['Schema\\DropTable']['temporaryOnly'] = ['bool'];
    }
    $properties['Schema\\TableName'] = ['schema' => ['Identifier'], 'table' => ['Identifier']];
    $methods['Schema\\DropTable'] = $methods['Select'];
    $methods['Schema\\TableName'] = ['__construct', 'quoted'];
    $internal[] = 'Schema\\DropTableLowering';
    $properties['Transaction\\Transaction'] = [
        'command' => ['Transaction\\TransactionCommand'], 'name' => ['?Identifier'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    $properties['Transaction\\TransactionCommand'] = $methods['Transaction\\TransactionCommand'] = [];
    $methods['Transaction\\Transaction'] = $methods['Select'];
    $enumCases['Transaction\\TransactionCommand'] = [
        'Begin' => 'begin', 'Commit' => 'commit', 'Rollback' => 'rollback',
        'Savepoint' => 'savepoint', 'Release' => 'release', 'RollbackTo' => 'rollback-to',
    ];
    $internal[] = 'Transaction\\TransactionLowering';
    $methods['Predicate\\IntegerComparison'] = ['__construct'];
    $methods['Predicate\\IntegerOperator'] = [];
    $enumCases['Predicate\\IntegerOperator'] = ['Equal' => '=', 'NotEqual' => '<>', 'Less' => '<', 'LessOrEqual' => '<=', 'Greater' => '>', 'GreaterOrEqual' => '>='];
}
$snapshot = $platform . '\\Model\\Statement\\Snapshot';
$allowed = [
    $snapshot, 'SqlSemantics\\SemanticsIR',
    $platform . '\\Model\\Diagnostic\\Check',
    $platform . '\\Model\\Statement\\Identifier\\Comparison',
    $platform . '\\Model\\Contract\\GrammarRelease',
    ...array_map(static fn ($short) => $prefix . $short, [...array_keys($properties), ...$pureCollaborators]),
];
$pureFunctions = ['strlen','str_contains','str_starts_with','preg_match','str_replace','ltrim','strcmp','count','isset','implode','array_pop','array_push','array_merge','array_is_list','is_object','strpbrk','substr_count','str_repeat','bin2hex'];

function requireShape(bool $condition, string $detail): void
{
    if (!$condition) {
        throw new RuntimeException($detail);
    }
}

function types($type, string $prefix, string $self): array
{
    if ($type instanceof PhpParser\Node\NullableType) {
        return ['?' . types($type->type, $prefix, $self)[0]];
    }
    $members = $type instanceof UnionType ? $type->types : [$type];
    $names = array_map(static fn ($member) => $member->toString() === 'self' ? $self : str_replace($prefix, '', $member->toString()), $members);
    sort($names);
    return $names;
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$traverser = new NodeTraverser(new NameResolver());
$finder = new NodeFinder();
try {
    $package = 'sql-semantics-' . $dialect;
    $facadePath = $package . '/src/Semantics.php';
    $patchedFacade = $overlay === '' ? '' : $overlay . '/' . $facadePath;
    $facadeFile = is_file($patchedFacade) ? $patchedFacade : $root . '/packages/' . $facadePath;
    $facadeAst = $traverser->traverse($parser->parse(file_get_contents($facadeFile)));
    $facade = $finder->findFirstInstanceOf($facadeAst, Class_::class);
    requireShape($facade instanceof Class_ && $facade->isFinal() && $facade->extends === null
        && $facade->namespacedName->toString() === $platform . '\\Semantics', 'The complete façade must be a final dialect class');
    requireShape(array_map(static fn ($name) => $name->toString(), $facade->implements) === ['SqlSemantics\\Analyzer'], 'Unexpected complete façade interface');
    $publication = __DIR__ . '/publication-v7.json';
    requireShape(
        hash_file('sha256', $publication) === 'af60a77bec1c74a88be03002fa8ce0c08a82c317ad181bbe49d85f61d7b8e7c8',
        'Publication contract differs from the recorded revision'
    );
    $surface = json_decode(file_get_contents($publication), true, 512, JSON_THROW_ON_ERROR)['surface'];
    $publicMethods = array_values(array_map(
        static fn ($method) => $method->name->toString(),
        array_filter($facade->getMethods(), static fn ($method) => $method->isPublic())
    ));
    requireShape($publicMethods === $surface, 'Unexpected public façade methods');
    requireShape(!is_file($root . '/packages/' . $package . '/src/Model/Statement/Operation.php')
        && !is_file($root . '/packages/sql-semantics/src/Query.php'), 'Obsolete publication type is present');
    $printer = new Standard();
    $constructor = '$platform = \\' . $platform . '\\Platform::shared();'
        . '$this->profile = $platform->profile($grammarVersion, $mode, $parameters);'
        . '$this->parser = $platform->parser($this->profile);'
        . '$platform->context($this->profile, $searchPath?->schemas, [], false);'
        . ($dialect === 'postgres' ? '$database = $currentDatabase === null ? null : new \\' . $platform . '\\Model\\Statement\\Identifier\\Name($currentDatabase->value);' : '')
        . '$this->catalog = new \\' . $platform . ($dialect === 'postgres' ? '\\Catalog' : '\\Model\\Facade\\Catalog')
        . '($this->profile, $searchPath?->schemas, $this->parser' . ($dialect === 'postgres' ? ', $database' : '') . ');'
        . '$this->binder = new \\' . $platform . '\\Model\\Facade\\StatementBinder($this->parser);';
    requireShape($printer->prettyPrint($facade->getMethod('__construct')->stmts)
        === $printer->prettyPrint($parser->parse('<?php ' . $constructor)), 'Unexpected façade initialization');
    $resolve = '$declarations = $this->catalog->resolve($context);';
    $bind = '$this->binder->resolve($sql, $declarations)';
    $kind = $dialect === 'mysql' ? 'Dml' : 'Manipulation';
    $insert = $dialect === 'mysql' ? 'Dml\\Insert\\InsertRows' : 'Manipulation\\InsertRows';
    $compound = $dialect === 'mysql' ? 'Query\\Set\\SetOperation' : 'Query\\SetOperation';
    $transactionNamespace = $platform . ($dialect === 'mysql' ? '\\Statement\\Server\\Transaction\\' : '\\Statement\\Utility\\Transaction\\');
    $transactionTypes = $dialect === 'mysql'
        ? ['Begin', 'Commit', 'Rollback', 'StartTransaction', 'Savepoint', 'ReleaseSavepoint', 'RollbackToSavepoint']
        : ['Begin', 'Commit', 'Rollback', 'SavepointCommand'];
    $transactions = implode(', ', array_map(static fn ($type) => '$statement instanceof \\' . $transactionNamespace . $type, $transactionTypes));
    $recursiveWith = $dialect === 'mysql'
        ? '$statement->with instanceof \\' . $platform . '\\Statement\\Query\\With\\With && $statement->with->recursive'
        : '$statement->with?->recursive === true';
    $analyze = $resolve . ' $operation = ' . $bind . '; $statement = $operation->statement;'
        . ($dialect === 'mysql' ? ' $relationChange = new \\' . $prefix . 'Schema\\Alter\\RelationChangeLowering();' : '')
        . ' return match (true) {'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\QueryExpression && ' . $recursiveWith . ' => (new \\'
        . $prefix . 'Rows\\Recursive\\RecursiveSeriesLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\QueryExpression && $statement->with !== null'
        . ' => (new \\' . $prefix . 'Rows\\With\\WithLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select && $statement->from instanceof \\' . $platform
        . '\\Statement\\Relation\\DerivedTable => (new \\' . $prefix . 'Rows\\LocalProjectionLowering())->lower($operation),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $compound . ', $statement instanceof \\' . $platform
        . '\\Statement\\Query\\ParenthesizedQuery => (new \\' . $prefix . 'Rows\\CompoundLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select && (new \\' . $prefix . 'Aggregation\\GroupedLowering())->applies($statement) => (new \\' . $prefix . 'Aggregation\\GroupedLowering())->lower($operation, $statement),'
        . ($dialect === 'postgres' ? '$statement instanceof \\' . $platform . '\\Statement\\Query\\ValuesList => (new \\' . $prefix
            . 'Rows\\Values\\ValuesLowering())->lower($operation, $statement),' : '')
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $kind . '\\Delete => (new \\' . $prefix . 'Mutation\\DeleteLowering())->lower($operation, $statement),'
        . ($dialect === 'mysql' ? '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateIndex => (new \\'
            . $prefix . 'Schema\\Index\\CreateIndexLowering())->lower($operation, $statement),' : '')
        . ($dialect === 'mysql'
            ? '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\CreateTableLike, $statement instanceof \\' . $platform
                . '\\Statement\\View\\CreateView => (new \\' . $prefix . 'Schema\\CreateRelationLowering())->lower($operation, $statement),'
            : '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\CreateTableAs, $statement instanceof \\' . $platform . '\\Statement\\Table\\View\\CreateView, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\View\\CreateMaterializedView, $statement instanceof \\' . $platform . '\\Statement\\Table\\Index\\CreateIndex, '
                . '$statement instanceof \\' . $platform . '\\Statement\\Catalog\\Schema\\CreateSchema, $statement instanceof \\'
                . $platform . '\\Statement\\Table\\Sequence\\CreateSequence => (new \\'
                . $prefix . 'Schema\\RelationDeclarationLowering())->lower($operation, $statement),')
        . ($dialect === 'postgres' ? '$statement instanceof \\' . $platform . '\\Statement\\Table\\View\\RefreshMaterializedView => (new \\'
            . $prefix . 'Schema\\View\\RefreshMaterializedViewLowering())->lower($operation, $statement),' : '')
        . ($dialect === 'postgres'
            ? '$statement instanceof \\' . $platform . '\\Statement\\Object\\Drop, $statement instanceof \\' . $platform . '\\Statement\\Object\\Rename, $statement instanceof \\'
                . $platform . '\\Statement\\Table\\TruncateTable, $statement instanceof \\' . $platform . '\\Statement\\Utility\\Maintenance\\Reindex'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Object\\ChangeOwner'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Object\\SetSchema'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Table\\Sequence\\AlterSequence'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Table\\Alter\\AlterTable'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Catalog\\Access\\Privilege\\Grant'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Catalog\\Access\\Privilege\\Revoke'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Catalog\\Access\\Defaults\\AlterDefaultPrivileges'
                . ' => (new \\' . $prefix . 'Schema\\Alter\\RelationChangeLowering())->lower($operation, $statement),'
            : '$relationChange->applies($statement) => $relationChange->lower($operation, $statement),')
        . '$statement instanceof \\' . $platform . '\\Statement\\'
        . ($dialect === 'mysql' ? 'Dml\\Insert\\InsertQuery' : 'Manipulation\\InsertSelect')
        . ' => (new \\' . $prefix . 'Mutation\\InsertLowering())->lowerQuery($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $insert . ' => (new \\' . $prefix . 'Mutation\\InsertLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $kind . '\\Update => (new \\' . $prefix . 'Mutation\\UpdateLowering())->lower($operation, $statement),'
        . $transactions . ' => (new \\' . $prefix . 'Transaction\\TransactionLowering())->lower($operation),'
        . 'default => (new \\' . $prefix . 'SelectLowering())->lower($operation), };';
    if ($dialect === 'sqlite') {
        $analyze = $resolve . ' $operation = ' . $bind . '; $statement = $operation->statement; return match (true) {'
            . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select && (new \\' . $prefix . 'Aggregation\\GroupedLowering())->applies($statement) => (new \\' . $prefix . 'Aggregation\\GroupedLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select, $statement instanceof \\' . $platform . '\\Statement\\Query\\With\\WithQuery => (new \\' . $prefix . 'SelectLowering())->lower($operation),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Compound => (new \\' . $prefix . 'Rows\\CompoundLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Mutation\\Delete => (new \\' . $prefix . 'Mutation\\DeleteLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Mutation\\InsertRows, $statement instanceof \\' . $platform . '\\Statement\\Mutation\\InsertSelect, $statement instanceof \\' . $platform . '\\Statement\\Mutation\\InsertDefaults => (new \\' . $prefix . 'Mutation\\InsertLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Mutation\\Update => (new \\' . $prefix . 'Mutation\\UpdateLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Query\\ValuesClause => (new \\' . $prefix . 'Rows\\ValuesLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\Drop => (new \\' . $prefix . 'Schema\\DropLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateTable,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateIndex,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateTableAs,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Trigger\\CreateTrigger,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateView => (new \\' . $prefix . 'Schema\\DefinitionLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterRenameColumn, $statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterRenameTable => (new \\' . $prefix . 'Schema\\Alter\\RenameLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterAddColumn => (new \\' . $prefix . 'Schema\\Alter\\AddColumnLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterDropColumn => (new \\' . $prefix . 'Schema\\Alter\\DropColumnLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Analyze,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Reindex,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Pragma,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Vacuum => (new \\' . $prefix . 'Maintenance\\MaintenanceLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Connection\\Attach,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Connection\\Detach => (new \\' . $prefix . 'Connection\\ConnectionLowering())->lower($operation, $statement),'
            . 'default => (new \\' . $prefix . 'Transaction\\TransactionLowering())->lower($statement), };';
    }
    sort($roots);
    $contextBody = 'if ($declarations === null) { return $this->catalog->build(null, $complete); }'
        . '\\' . $platform . '\\Model\\Diagnostic\\Check::input(array_is_list($declarations), \'Catalog definitions must form a list.\');'
        . '$definitions = []; foreach ($declarations as $definition) {'
        . '\\' . $platform . '\\Model\\Diagnostic\\Check::input(is_string($definition), \'Catalog definitions must be SQL strings.\');'
        . '$definitions[] = $definition; } return $this->catalog->build($definitions, $complete);';
    $routes = [
        'analyze' => [$roots, $analyze],
        'context' => [['SqlSemantics\\AnalysisContext'], $contextBody],
        'analyzeAll' => [['array'], $resolve . ' $results = [];'
            . 'foreach ($this->split($sql) as $statement) { $results[] = $this->analyze($statement, $declarations); } return $results;'],
    ];
    foreach ($routes as $name => [$return, $body]) {
        $method = $facade->getMethod($name);
        requireShape($method !== null && $method->isPublic() && !$method->isStatic()
            && $method->returnType !== null && types($method->returnType, $prefix, 'Semantics') === $return, "Publication signature differs in $name");
        requireShape($printer->prettyPrint($method->stmts) === $printer->prettyPrint($parser->parse('<?php ' . $body)), "Publication body differs in $name");
    }
    $primitives = json_decode(file_get_contents(__DIR__ . '/value-primitives.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($primitives['artifacts'][$dialect] as $path => $approved) {
        $patched = $overlay === '' ? '' : $overlay . '/' . $package . '/' . $path;
        $file = is_file($patched) ? $patched : $root . '/packages/' . $package . '/' . $path;
        requireShape(is_file($file) && hash_file('sha256', $file) === $approved, "Value primitive differs from the recorded baseline: $path");
    }
    if ($dialect === 'sqlite') {
        $contracts = [
            'sqlite-rename-boundaries-v2.json' => 'f6fccd438228146e360b5dffb17aa17e16342a09069faef53d2a7b05ad08ddf3',
            'sqlite-add-column-boundaries-v5.json' => 'b33bcc1f10e3cca71f4dfc4ce1b5e2098a4fc817abfb41acd78c212b5c2c7c76',
        ];
        foreach ($contracts as $name => $digest) {
            $boundary = __DIR__ . '/' . $name;
            requireShape(hash_file('sha256', $boundary) === $digest, 'SQLite ALTER TABLE boundary contract differs from its fixed revision');
            $sources = json_decode(file_get_contents($boundary), true, 512, JSON_THROW_ON_ERROR)['artifacts'];
            foreach ($sources as $path => $expected) {
                $patched = $overlay === '' ? '' : $overlay . '/' . $package . '/' . $path;
                $file = is_file($patched) ? $patched : $root . '/packages/' . $package . '/' . $path;
                requireShape(is_file($file) && hash_file('sha256', $file) === $expected, "SQLite ALTER TABLE binding source differs: $path");
            }
        }
    }
    $checkedBindingSources = [];
    if ($dialect === 'sqlite') {
        $path = __DIR__ . '/sqlite-view-binding-sources-v2.json';
        requireShape(hash_file('sha256', $path) === 'd2c6fb4e1c60a7493fe40d51be601bab5cf470fed71578fd893bd66356ac3e6f', 'SQLite view binding contract differs from its fixed revision');
        $sources = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['artifacts'];
        foreach ($sources as $relative => $expected) {
            $patched = $overlay === '' ? '' : $overlay . '/' . $package . '/' . $relative;
            $file = is_file($patched) ? $patched : $root . '/packages/' . $package . '/' . $relative;
            requireShape(is_file($file) && hash_file('sha256', $file) === $expected, "SQLite view binding source differs: $relative");
            $checkedBindingSources[$relative] = ['sha256' => $expected, 'contract' => basename($path)];
        }
    }
    $bindings = match ($dialect) {
        'sqlite' => [
            ['sqlite-create-index-binding-v2.json', '518f25e19be787f59726f4d9783d029c5a77bc6c5b8bb97103aeebbba3c7fd69', 'Index binding'],
            ['sqlite-index-expression-binding-v1.json', '0f51026989e87bb830f5a9fac584794e318c410fcc85744079e38839e8e7a255', 'Native index expression binding'],
            ['sqlite-drop-column-binding-v6.json', 'af0239a7ec580713e889f6568ecff825d6a0c1527d0cd4383e7040162a2fa55a', 'Column removal binding'],
            ['sqlite-drop-index-binding-v6.json', 'c2fbfe1a46cf1d4efacaadb803e9cdccdaf75aa0401f97920301f6531c3fff06', 'Index removal binding'],
            ['sqlite-constraint-index-binding-v4.json', '22d8e3b6a6bd80dc8e3bc5d6fbe57c360a90a80cc48fc4336819d6cb71b9f6a9', 'Constraint index binding'],
            ['sqlite-reindex-binding-v5.json', '2841be5618d282174f012bdc1c96eaccac5dfd64a65ee9882762c7bbdb48c039', 'Reindex binding'],
            ['sqlite-analyze-statistics-binding-v5.json', 'e34dd9b081e55a22b68bed8d9f25b97ab5a42dd3d0101707d9b201f6bea10557', 'Analyze statistics binding'],
            ['sqlite-flag-pragmas-binding-v4.json', '7947b1414c41c211ca79ae102b8e576afdc7b91effcf5539f6aa7dc18c1ddb61', 'Flag pragma binding'],
            ['sqlite-folded-binding-sources-v11.json', 'c0878a2e8b2d4c0f27659feba880e10cccd398f4d13d2ba4063d630ee495003d', 'Folded binding'],
            ['sqlite-connection-commands-binding-v1.json', '4e048dce7881dd175ff0be0b027010a69e1b8c4d495e09f4d7329bfb7073aed9', 'Connection operand binding'],
            ['sqlite-audit-triggers-binding-v5.json', '25a87a37c6dcaff9704a9cb2c2d014855eef17e89c97b1545bee43d28da28f90', 'SQLite trigger binding'],
            ['sqlite-drop-triggers-binding-v4.json', 'f19956198c9bfaf7146f83dc80f9aea9550d4d6b11f182631ef847434cee16a3', 'SQLite trigger removal binding'],
            ['sqlite-merged-join-binding-v3.json', '08154530910f68b6a551b2278a048541c0ed919e557f00af01e6f34f6da2e6c4', 'Merged join binding'],
        ],
        'postgres' => [
            ['postgres-join-binding-v3.json', '5195458168a04370df438fcebfc8b81aa3877fed56f16f72c256ccac96bf0509', 'PostgreSQL join binding'],
            ['postgres-declaration-binding-sources-v21.json', 'afd456fecc404053b0b0f0a695eaea123057eb268ddab7f7daea94fad14f4796', 'PostgreSQL declaration binding'],
            ['postgres-integer-binding-sources-v1.json', '108d4ad1de0b83709687beaa2c9f405e4f2accae0b5c19d275b6eaf40dd9f738', 'Integer binding'],
            ['postgres-output-order-binding-v1.json', 'e3bbcfd8480341a01b817401a6612bd642640ddc727dca066fdc12ce05e9d1e7', 'PostgreSQL output order binding'],
        ],
        'mysql' => [
            ['mysql-from-binding-v2.json', 'd46f12a3f933d01aa286fc9ccf2ece3aae09713a6cfe22b86ca861d288460741', 'MySQL FROM binding'],
            ['mysql-text-literal-binding-v1.json', '9ae75f8c5ee63d23788c813d0552cc10fb12096133d4c76cc93321508a6f7329', 'MySQL text binding'],
            ['mysql-varchar-binding-v7.json', '1304d4803619cd8fc849431ec1a8f3aed53f838e09767182feb9692f374f70e1', 'MySQL VARCHAR binding'],
            ['mysql-ordered-table-rename-binding-v6.json', 'a55e5a9d21ea68213ff5c4609f4c24c2a12f154b03940bf69c938ebe2ed3ba26', 'MySQL ordered rename binding'],
            ['mysql-session-table-binding-v7.json', 'd017b6d49d5c1ddd47a7b26c226b8bc052460a903c162bcffd5d9a8aefae30f8', 'MySQL session table binding'],
            ['mysql-native-index-binding-v7.json', '38e230f70d2b36a9888464b671cfd8e5182ef9c598ca09f31c2ee9dba5c0b61f', 'MySQL native index binding'],
            ['mysql-drop-view-binding-v4.json', 'e9c6e4dd82ac7551a716f5a0924dd325a76b82b75eb0b899d07d758f7fdeb5ee', 'MySQL view removal binding'],
            ['mysql-create-primary-binding-v1.json', '83770e7c7605a81930b1d924dd3090cebcdb754788b3302791e04948c99fe7b3', 'MySQL primary key binding'],
        ],
    };
    $providedBindings = [];
    $deferredBindings = [];
    $bindingDependencies = [];
    foreach ($bindings as [$name, $digest, $label]) {
        $bindingRecord = __DIR__ . '/' . $name;
        requireShape(hash_file('sha256', $bindingRecord) === $digest, "$label contract differs from its recorded revision");
        $record = json_decode(file_get_contents($bindingRecord), true, 512, JSON_THROW_ON_ERROR);
        $bindingSources = $record['artifacts'];
        $bindingDependencies[$name] = $record['sharedBindings'] ?? [];
        foreach ($bindingSources as $path => $approved) {
            $patched = $overlay === '' ? '' : $overlay . '/' . $package . '/' . $path;
            $file = is_file($patched) ? $patched : $root . '/packages/' . $package . '/' . $path;
            $message = "$label source differs in $path";
            if (isset($approved['inspectionOrder'])) {
                requireShape(str_starts_with($path, 'src/Ir/') && $approved['inspectionOrder'] === 'after-method-boundaries',
                    'Only checked IR sources can defer the byte audit until after the method inspection.');
                $deferredBindings[] = [$file, $approved['sha256'], $message];
            } else {
                requireShape(is_file($file) && hash_file('sha256', $file) === $approved['sha256'], $message);
            }
            $checkedBindingSources[$path] = $approved;
            $providedBindings[$name][$path] = $approved['sha256'];
        }
    }
    $checkedBindingDependencies = [];
    foreach ($bindingDependencies as $consumer => $requirements) {
        foreach ($requirements as $source => $requirement) {
            $provider = $requirement['contract'];
            requireShape(
                ($providedBindings[$provider][$source] ?? null) === $requirement['sha256'],
                "Binding dependency differs: $consumer references $source in $provider"
            );
            $checkedBindingDependencies[$consumer][$source] = $requirement;
        }
    }
    $base = $package . '/src/Ir';
    $storagePath = __DIR__ . '/value-storage-v73.json';
    requireShape(
        hash_file('sha256', $storagePath) === '229a0450ee240af9aedc213e7bf2c6ca87e889b05e41dcaac93d877a8279abdb',
        'Value storage contract differs from the recorded revision'
    );
    $storage = json_decode(file_get_contents($storagePath), true, 512, JSON_THROW_ON_ERROR)['dialects'][$dialect];
    if ($dialect === 'mysql') {
        foreach ([
            ['mysql-index-namespace-storage-v1.json', '90a0725227d5c8931a4b9cb2ea92567cb4a93798161a81590874659da33c412d'],
            ['mysql-index-auto-increment-storage-v1.json', '6b6a146f979bb53a4dce55a241f642a75e4fdb61d541c7ba254ae158aa3344c7'],
            ['mysql-index-foreign-key-storage-v1.json', '53ab347ce233a749cd3ec986c1005857615bf16e9f80e7b7d18f3c7aefebec7e'],
            ['mysql-index-visibility-storage-v1.json', '3b9a4fb0ba21938c1ba8af07b9e8a906c111c4b2117e2a42f6429c85e4e49727'],
            ['mysql-index-removal-set-storage-v1.json', 'ed343981fe207cd80b7d6628cdd6685787c39f241bfc5bc27cee629e80e243a1'],
            ['mysql-index-rename-storage-v1.json', 'ffc8cd41e898f15ecae3343c9dfdcfbe1a72678249e21fb2edbe8d02c1d73831'],
            ['mysql-mixed-index-storage-v1.json', '281216083a6a07e02dc27a5730a9b347b9f512f77e4507b1037a39557a28490f'],
        ] as [$catalogue, $digest]) {
            $indexStorage = __DIR__ . '/' . $catalogue;
            requireShape(hash_file('sha256', $indexStorage) === $digest, 'MySQL native index list storage differs from its recorded revision');
            $indexRecord = json_decode(file_get_contents($indexStorage), true, 512, JSON_THROW_ON_ERROR);
            $current = $storage[$indexRecord['owner']] ?? [];
            requireShape(array_intersect_key($current, $indexRecord['storage']) === [], 'Duplicate MySQL native index list storage rule');
            $storage[$indexRecord['owner']] = $current + $indexRecord['storage'];
        }
        ksort($storage);
    }
    $listFields = [];
    $checkedValues = [];
    $checkedDispatch = [];
    $directory = $root . '/packages/' . $base . '/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    $observed = [];
    $boundaries = nativeBoundaryContract($dialect);
    $checkedBoundaries = [];
    foreach ($files as $entry) {
        if (!$entry->isFile() || $entry->getExtension() !== 'php') {
            continue;
        }
        $file = $entry->getPathname();
        $relative = substr($file, strlen($directory));
        $short = str_replace('/', '\\', substr($relative, 0, -4));
        $patched = $overlay === '' ? '' : $overlay . '/' . $base . '/' . $relative;
        $ast = $traverser->traverse($parser->parse(file_get_contents(is_file($patched) ? $patched : $file)));
        foreach ($ast as $statement) {
            requireShape($statement instanceof Stmt\Declare_ || $statement instanceof Stmt\Namespace_, "Executable code outside a namespace in $short");
            if ($statement instanceof Stmt\Declare_) {
                requireShape($statement->stmts === null && count($statement->declares) === 1
                    && $statement->declares[0]->key->toString() === 'strict_types'
                    && $statement->declares[0]->value instanceof PhpParser\Node\Scalar\Int_
                    && $statement->declares[0]->value->value === 1, "Unexpected PHP execution directive in $short");
            }
            if ($statement instanceof Stmt\Namespace_) {
                foreach ($statement->stmts as $member) {
                    requireShape($member instanceof Stmt\Use_ || $member instanceof ClassLike || $member instanceof Stmt\Nop, "Executable namespace member in $short");
                }
            }
        }
        $classes = $finder->findInstanceOf($ast, ClassLike::class);
        requireShape(count($classes) === 1 && $classes[0]->namespacedName->toString() === $prefix . $short, "Unexpected declared type in $short");
        $class = $classes[0];
        $observed[] = $short;
        $sqliteMode = match ($short) {
            'SelectSql' => 'preserveEmptyMembership',
            'ExpressionLowering' => 'indexSyntax',
            default => null,
        };
        if ($dialect === 'sqlite' && $sqliteMode !== null) {
            $parameters = $class->getMethod('__construct')?->params ?? [];
            $parameter = $parameters[0] ?? null;
            requireShape(count($parameters) === 1 && $parameter->var->name === $sqliteMode
                && $parameter->type instanceof PhpParser\Node\Identifier && $parameter->type->toString() === 'bool'
                && $parameter->flags === (Stmt\Class_::MODIFIER_PRIVATE | Stmt\Class_::MODIFIER_READONLY)
                && $parameter->default instanceof Expr\ConstFetch && $parameter->default->name->toLowerString() === 'false'
                && !$parameter->byRef && !$parameter->variadic, "Unexpected SQLite index form parameter in $short");
        }
        if ($short === 'Maintenance\\Reclaim\\PageReclaimLimit') {
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $definition = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPublic()
                && $definition->name->toString() === 'MAXIMUM' && $printer->prettyPrintExpr($definition->value) === '2147483647',
                'The SQLite incremental vacuum limit differs');
        }
        if ($short === 'Maintenance\\Page\\PageLimit') {
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $definition = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPublic()
                && $definition->name->toString() === 'MAXIMUM' && $definition->value instanceof String_
                && $definition->value->value === '4294967294', 'The SQLite native page-limit range differs');
        }
        if ($short === 'Maintenance\\Integrity\\IntegrityLimit') {
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $present = [];
            foreach ($constants as $constant) {
                requireShape(count($constant->consts) === 1 && $constant->isPublic(), 'Unexpected SQLite integrity constant');
                $definition = $constant->consts[0];
                $present[$definition->name->toString()] = $printer->prettyPrintExpr($definition->value);
            }
            requireShape($present === ['DEFAULT_VALUE' => '100', 'MAX_VALUE' => '2147483647'], 'The SQLite integrity range differs');
        }
        if ($dialect === 'postgres' && $short === 'Schema\\Alter\\RenameColumn') {
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $system = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPrivate()
                && $system->name->toString() === 'SYSTEM_COLUMNS' && $system->value instanceof Expr\Array_
                && $printer->prettyPrintExpr($system->value) === "['tableoid', 'xmin', 'cmin', 'xmax', 'cmax', 'ctid']",
                'The PostgreSQL system column set differs');
        }
        if ($dialect === 'sqlite' && $short === 'Schema\\Alter\\RenameColumn') {
            $lexical = __DIR__ . '/sqlite-rename-v4.json';
            requireShape(hash_file('sha256', $lexical) === '10291e93677e2e133e691a175dc16a2e78039dccabc6eeefef8534402f148861', 'SQLite rename token contract differs');
            $required = json_decode(file_get_contents($lexical), true, 512, JSON_THROW_ON_ERROR)['lexicalDomain']['bareRejected'];
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $set = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPrivate()
                && $set->name->toString() === 'RESERVED_DESTINATIONS' && $set->value instanceof Expr\Array_, 'SQLite rename token registry differs');
            $names = [];
            foreach ($set->value->items as $token) {
                requireShape($token->key === null && $token->value instanceof String_ && !$token->unpack, 'Invalid SQLite rename token registry entry');
                $names[] = $token->value->value;
            }
            requireShape($names === $required, 'SQLite reserved destination tokens differ');
        }
        if ($dialect === 'sqlite' && $short === 'Schema\\Predicate\\ImplicitCheckLabel') {
            $lexical = __DIR__ . '/sqlite-implicit-checks-v1.json';
            requireShape(hash_file('sha256', $lexical) === '94101eaf66395a96c32aca5cc6e4b79f210b0436962f1712c08e0fc14f75b766', 'SQLite implicit CHECK token contract differs');
            $required = json_decode(file_get_contents($lexical), true, 512, JSON_THROW_ON_ERROR)['lexicalDomain']['bareRejected'];
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $map = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPrivate()
                && $map->name->toString() === 'NON_COLUMN_KEYWORDS' && $map->value instanceof Expr\Array_, 'SQLite CHECK token registry differs');
            $observedKeys = [];
            foreach ($map->value->items as $item) {
                requireShape(
                    $item->key instanceof String_ && !$item->unpack && !$item->byRef
                    && $item->value instanceof Expr\ConstFetch && $item->value->name->toLowerString() === 'true',
                    'Invalid SQLite CHECK token registry entry'
                );
                $observedKeys[] = $item->key->value;
            }
            requireShape($observedKeys === array_map('strtolower', $required), 'SQLite CHECK token meanings differ');
        }
        if ($dialect === 'mysql' && $short === 'IntegerLiteral') {
            $constants = $finder->findInstanceOf($class->stmts, Stmt\ClassConst::class);
            $width = $constants[0]->consts[0] ?? null;
            requireShape(count($constants) === 1 && count($constants[0]->consts) === 1 && $constants[0]->isPublic()
                && $width->name->toString() === 'MAX_LEADING_ZEROES' && $width->value instanceof PhpParser\Node\Scalar\Int_
                && $width->value->value === 32, 'The MySQL integer field width differs');
        }
        if (in_array($short, $internal, true)) {
            requireShape($class instanceof Class_ && $class->isFinal() && $class->extends === null, "Open IR collaborator $short");
            $interfaces = array_map(static fn ($interface) => $interface->toString(), $class->implements);
            requireShape($interfaces === [], "Unexpected collaborator interface in $short");
            $loweringRole = match ($short) {
                'Aggregation\\GroupedLowering' => 'Aggregation\\GroupedSelect',
                'SelectLowering' => 'Select',
                'Rows\\LocalProjectionLowering' => 'Rows\\LocalProjection',
                'Rows\\Values\\ValuesLowering' => 'Rows\\Values\\ValuesQuery',
                'Rows\\With\\WithLowering' => 'Rows\\With\\WithProjection',
                'Rows\\Recursive\\RecursiveSeriesLowering' => 'Rows\\RecursiveSeries',
                'Schema\\CreateTableLowering' => 'Schema\\CreateTable',
                'Schema\\Like\\CreateTableLikeLowering' => 'Schema\\Like\\CreateTableLike',
                'Schema\\Alter\\TableRenameLowering' => 'Schema\\Alter\\RenameTable',
                'Schema\\Truncate\\TruncateLowering' => 'Schema\\Truncate\\TruncateTable',
                'Schema\\Query\\CreateTableAsLowering' => 'Schema\\Query\\CreateTableAs',
                'Schema\\View\\CreateViewLowering' => 'Schema\\View\\CreateView',
                'Schema\\View\\CreateMaterializedViewLowering' => 'Schema\\View\\CreateMaterializedView',
                'Schema\\View\\RefreshMaterializedViewLowering' => 'Schema\\View\\RefreshMaterializedView',
                'Schema\\CreateColumnLowering' => 'Schema\\TableColumn',
                'Schema\\DeclaredDatatypeLowering' => 'Schema\\DeclaredDatatypeName',
                'Expression\\Nulls\\CoalesceLowering' => 'Expression\\Nulls\\Coalesce',
                'Schema\\Generation\\GeneratedLowering' => 'Schema\\Generation\\ColumnGeneration',
                'Schema\\DropTableLowering' => 'Schema\\DropTable',
                'Schema\\Alter\\DropColumnLowering' => 'Schema\\Alter\\DropColumn',
                'Schema\\Index\\CreateIndexLowering' => 'Schema\\Index\\CreateIndex',
                'Schema\\Index\\Btree\\CreateIndexLowering' => 'Schema\\Index\\Btree\\CreateIndex',
                'Schema\\Index\\Btree\\IndexTableLowering' => 'TableSource',
                'Schema\\Index\\DropIndexLowering' => 'Schema\\Index\\DropIndex',
                'Schema\\Trigger\\Binding\\DropTriggerLowering' => 'Schema\\Trigger\\DropTrigger',
                'Schema\\Trigger\\Binding\\TriggerRaiseLowering' => 'Schema\\Trigger\\Program\\TriggerRaise',
                'Maintenance\\ReindexLowering' => 'Maintenance\\Reindex',
                'Maintenance\\AnalyzeLowering' => 'Maintenance\\Analyze',
                'Maintenance\\FlagPragmaLowering' => 'Maintenance\\FlagPragma',
                'Maintenance\\Metadata\\ForeignKeyAuditLowering' => 'Maintenance\\Metadata\\ForeignKeyAudit',
                'Maintenance\\Integrity\\IntegrityAuditLowering' => 'Maintenance\\Integrity\\IntegrityAudit',
                'Maintenance\\Header\\HeaderPragmaLowering' => 'Maintenance\\Header\\HeaderPragma',
                'Maintenance\\Vacuum\\VacuumLowering' => 'Maintenance\\Vacuum\\Vacuum',
                'Maintenance\\Page\\PagePragmaLowering' => 'Maintenance\\Page\\PagePragma',
                'Schema\\View\\DropViewLowering' => 'Schema\\View\\DropView',
                'Transaction\\TransactionLowering' => 'Transaction\\Transaction',
                default => null,
            };
            if ($short === 'Maintenance\\MaintenanceLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Maintenance\\Analyze', 'Maintenance\\Cache\\CacheSize', 'Maintenance\\Cache\\CacheSpill', 'Maintenance\\Cache\\DefaultCacheSize', 'Maintenance\\FlagPragma', 'Maintenance\\Header\\HeaderPragma', 'Maintenance\\Integrity\\IntegrityAudit', 'Maintenance\\Inventory\\InventoryPragma',
                        'Maintenance\\Metadata\\ForeignKeyAudit', 'Maintenance\\Metadata\\IndexInspection', 'Maintenance\\Metadata\\TableInspection',
                        'Maintenance\\Metadata\\TableListing', 'Maintenance\\Page\\PagePragma', 'Maintenance\\Reclaim\\AutoVacuum', 'Maintenance\\Reclaim\\IncrementalVacuum', 'Maintenance\\Reindex', 'Maintenance\\Vacuum\\Vacuum',
                    ], 'Unexpected SQLite maintenance publication');
            }
            if ($short === 'Maintenance\\PragmaLowering' || $short === 'Maintenance\\Metadata\\TableMetadataLowering') {
                $members = $class->getMethods();
                $expected = $short === 'Maintenance\\PragmaLowering' ? ['lower'] : ['lower', 'target'];
                $methodNames = array_map(static fn ($method) => $method->name->toString(), $members);
                $public = array_values(array_filter($members, static fn ($method) => $method->isPublic() && !$method->isStatic()));
                $metadataRoots = ['Maintenance\\Metadata\\TableInspection', 'Maintenance\\Metadata\\TableListing'];
                $return = $short === 'Maintenance\\PragmaLowering' ? ['Maintenance\\Cache\\CacheSize', 'Maintenance\\Cache\\CacheSpill', 'Maintenance\\Cache\\DefaultCacheSize', 'Maintenance\\FlagPragma', 'Maintenance\\Header\\HeaderPragma', 'Maintenance\\Integrity\\IntegrityAudit', 'Maintenance\\Inventory\\InventoryPragma', 'Maintenance\\Metadata\\ForeignKeyAudit', 'Maintenance\\Metadata\\IndexInspection', ...$metadataRoots, 'Maintenance\\Page\\PagePragma', 'Maintenance\\Reclaim\\AutoVacuum', 'Maintenance\\Reclaim\\IncrementalVacuum'] : $metadataRoots;
                requireShape($methodNames === $expected && count($public) === count($expected)
                    && types($members[0]->returnType, $prefix, $short) === $return, 'Unexpected SQLite pragma publication');
            }
            if ($short === 'Connection\\ConnectionLowering' || $short === 'Connection\\ConnectionOperands') {
                $members = $class->getMethods();
                $expected = $short === 'Connection\\ConnectionLowering'
                    ? ['lower' => ['Connection\\Attach', 'Connection\\Detach']]
                    : ['row' => ['Rows\\ValueRow'], 'name' => ['Connection\\AttachmentName', 'Parameter']];
                requireShape(count($members) === count($expected), 'Unexpected SQLite connection route count');
                foreach ($members as $member) {
                    $method = $member->name->toString();
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === ($expected[$method] ?? null), 'Unexpected SQLite connection route');
                }
            }
            if ($short === 'Maintenance\\Cache\\CachePragmaLowering' || $short === 'Maintenance\\Cache\\SpillArgument') {
                $members = $class->getMethods();
                $expected = $short === 'Maintenance\\Cache\\CachePragmaLowering'
                    ? ['lower' => ['Maintenance\\Cache\\CacheSize', 'Maintenance\\Cache\\CacheSpill', 'Maintenance\\Cache\\DefaultCacheSize'],
                        'capacity' => ['Maintenance\\Cache\\CacheSize'], 'persistent' => ['Maintenance\\Cache\\DefaultCacheSize'],
                        'spilling' => ['Maintenance\\Cache\\CacheSpill']]
                    : ['read' => ['Maintenance\\Cache\\SpillMode', 'Maintenance\\Cache\\SpillThreshold']];
                requireShape(count($members) === count($expected), 'Unexpected SQLite cache route count');
                foreach ($members as $member) {
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === ($expected[$member->name->toString()] ?? null), 'Unexpected SQLite cache route');
                }
            }
            if ($short === 'Schema\\DefinitionLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\CreateTable', 'Schema\\Index\\CreateIndex', 'Schema\\Query\\CreateTableAs',
                        'Schema\\Trigger\\CreateTrigger', 'Schema\\View\\CreateView',
                    ], 'Unexpected SQLite declaration publication route');
            }
            if ($short === 'Schema\\Trigger\\Binding\\TriggerLowering' || $short === 'Schema\\Trigger\\Binding\\TriggerStepLowering'
                || $short === 'Schema\\Trigger\\Binding\\TriggerOperands' || $short === 'Schema\\Trigger\\Binding\\TriggerTargetLowering'
                || $short === 'Schema\\Trigger\\Binding\\TriggerMutationLowering' || $short === 'Schema\\Trigger\\Binding\\TriggerMutationOperands') {
                $expected = match ($short) {
                    'Schema\\Trigger\\Binding\\TriggerLowering' => ['lower' => ['Schema\\Trigger\\CreateTrigger'],
                        'watched' => ['TableSource'], 'selectors' => ['array'], 'schema' => ['Identifier'], 'program' => ['array']],
                    'Schema\\Trigger\\Binding\\TriggerStepLowering' => ['lower' => ['Schema\\Trigger\\TriggerInsert']],
                    'Schema\\Trigger\\Binding\\TriggerTargetLowering' => ['lower' => ['array']],
                    'Schema\\Trigger\\Binding\\TriggerMutationLowering' => ['lower' => ['Schema\\Trigger\\Mutation\\TriggerDelete', 'Schema\\Trigger\\Mutation\\TriggerUpdate'],
                        'conflict' => ['Mutation\\UpdateConflict']],
                    'Schema\\Trigger\\Binding\\TriggerMutationOperands' => [
                        '__construct' => null,
                        'value' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Schema\\Trigger\\Mutation\\TriggerTargetField', 'Schema\\Trigger\\TriggerField'],
                        'field' => ['Schema\\Trigger\\Mutation\\TriggerTargetField', 'Schema\\Trigger\\TriggerField'],
                        'filter' => ['Schema\\Trigger\\Mutation\\TriggerEquality', 'Schema\\Trigger\\Mutation\\TriggerTargetNullTest', 'Schema\\Trigger\\TriggerCondition', 'null'],
                        'nullTest' => ['Schema\\Trigger\\Mutation\\TriggerTargetNullTest', 'Schema\\Trigger\\TriggerCondition'],
                    ],
                    'Schema\\Trigger\\Binding\\TriggerOperands' => [
                        'value' => ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Schema\\Trigger\\TriggerField'],
                        'field' => ['Schema\\Trigger\\TriggerField'], 'condition' => ['?Schema\\Trigger\\TriggerCondition'],
                        'ungroup' => ['SqlSemantics\\Platform\\Sqlite\\Model\\Statement\\Scalar'],
                    ],
                };
                $members = $class->getMethods();
                requireShape(count($members) === count($expected), 'Unexpected SQLite trigger binding route count');
                foreach ($members as $member) {
                    $name = $member->name->toString();
                    requireShape($member->isPublic() && !$member->isStatic() && array_key_exists($name, $expected)
                        && ($member->returnType === null ? null : types($member->returnType, $prefix, $short)) === $expected[$name],
                        'Unexpected SQLite trigger binding route');
                    if ($name === '__construct') {
                        $parameters = [];
                        foreach ($member->params as $param) {
                            requireShape($param->flags === (Class_::MODIFIER_READONLY | Class_::MODIFIER_PRIVATE)
                                && !$param->byRef && !$param->variadic && $param->default === null,
                                'A transient trigger operand binder needs exact readonly promotion');
                            $parameters[$param->var->name] = $param->type?->toString();
                        }
                        requireShape($parameters === [
                            'operation' => $platform . '\\Model\\Facade\\BoundStatement',
                            'source' => $platform . '\\Statement\\Trigger\\TriggerTable',
                            'watched' => $prefix . 'TableSource', 'target' => $prefix . 'SourceBinding',
                        ], 'A transient trigger operand binder has unexpected dependencies');
                    }
                }
            }
            if ($short === 'Maintenance\\Reclaim\\ReclaimPragmaLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 2, 'Unexpected SQLite reclamation route count');
                $expected = ['automatic' => 'Maintenance\\Reclaim\\AutoVacuum', 'incremental' => 'Maintenance\\Reclaim\\IncrementalVacuum'];
                foreach ($members as $member) {
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === [$expected[$member->name->toString()] ?? null],
                        'Unexpected SQLite reclamation publication route');
                }
            }
            if ($short === 'Maintenance\\Page\\PageLimitArgument') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'read' && types($members[0]->returnType, $prefix, $short) === ['?Maintenance\\Page\\PageLimit'],
                    'Unexpected SQLite page-limit operand route');
            }
            if ($short === 'Expression\\Binding\\NumberedRegistrations') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'collect' && types($members[0]->returnType, $prefix, $short) === ['array'],
                    'Unexpected SQLite lexical registration route');
            }
            if ($short === 'Maintenance\\Argument\\NativePragmaInteger') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'parse' && types($members[0]->returnType, $prefix, $short) === ['?int'],
                    'Unexpected SQLite native pragma integer conversion route');
            }
            if ($short === 'Maintenance\\Integrity\\IntegrityNumber') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'limit' && types($members[0]->returnType, $prefix, $short) === ['?int'],
                    'Unexpected SQLite integrity report budget route');
            }
            if ($short === 'Maintenance\\Metadata\\IndexMetadataLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 2 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[1]->isPublic() && !$members[1]->isStatic()
                    && $members[1]->name->toString() === 'storage'
                    && types($members[0]->returnType, $prefix, $short) === ['Maintenance\\Metadata\\IndexInspection']
                    && types($members[1]->returnType, $prefix, $short) === ['?Maintenance\\IndexTarget'],
                    'Unexpected SQLite index metadata lowering');
            }
            if ($short === 'Maintenance\\PragmaScope') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'resolve' && types($members[0]->returnType, $prefix, $short) === ['?Identifier'],
                    'Unexpected SQLite pragma schema resolution');
            }
            if ($short === 'Maintenance\\MaintenanceLookup') {
                $public = array_values(array_map(
                    static fn ($method) => $method->name->toString(),
                    array_filter($class->getMethods(), static fn ($method) => $method->isPublic()),
                ));
                requireShape($public === ['scopes', 'relation', 'index', 'path', 'schema'], 'Unexpected SQLite maintenance lookup route');
            }
            if ($short === 'Schema\\Alter\\AddColumnLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['Schema\\Alter\\AddColumn', 'Schema\\Alter\\AddGeneratedColumn'],
                    'Unexpected SQLite column addition publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\Btree\\IndexPredicateLowering') {
                $family = ['Schema\\Index\\Btree\\IndexBooleanColumn', 'Schema\\Index\\Btree\\IndexBooleanPredicate',
                    'Schema\\Index\\Btree\\IndexTruthTest', 'Schema\\Index\\Btree\\NullIndexPredicate'];
                $returns = ['lower' => [...$family, 'null'], 'node' => $family, 'slot' => ['int']];
                $members = $class->getMethods();
                requireShape(count($members) === count($returns), 'Unexpected PostgreSQL partial-index predicate method count');
                foreach ($members as $member) {
                    $method = $member->name->toString();
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === ($returns[$method] ?? []),
                        'Unexpected PostgreSQL partial-index predicate publication');
                }
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\Btree\\IndexCondition') {
                $returns = ['check' => 'void', 'sql' => 'string'];
                $members = $class->getMethods();
                requireShape(count($members) === count($returns), 'Unexpected PostgreSQL partial-index condition method count');
                foreach ($members as $member) {
                    $method = $member->name->toString();
                    requireShape($member->isPublic() && $member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === [($returns[$method] ?? '')],
                        'Unexpected PostgreSQL partial-index condition traversal');
                }
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\Btree\\IndexOperands') {
                $methodsByName = $class->getMethods();
                $results = ['key' => 'Schema\\Index\\Btree\\IndexKey', 'covering' => 'Schema\\Index\\Btree\\IncludedColumn', 'slot' => 'int'];
                requireShape(count($methodsByName) === count($results), 'Unexpected PostgreSQL B-tree operand method count');
                foreach ($methodsByName as $member) {
                    $method = $member->name->toString();
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === [($results[$method] ?? '')],
                        'Unexpected PostgreSQL B-tree operand binding');
                }
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\IndexTargetLowering') {
                $members = $class->getMethods();
                $returns = [
                    'resolve' => ['Schema\\Index\\IndexReference', 'Schema\\Index\\MissingIndex'],
                    'resolveRebuild' => ['Schema\\Index\\ConstraintIndexReference', 'Schema\\Index\\ExclusionIndexReference', 'Schema\\Index\\IndexReference', 'Schema\\Index\\Partition\\PartitionIndexReference'],
                    'lookup' => ['?' . $platform . '\\Model\\Contract\\Index\\IndexDeclaration'],
                ];
                requireShape(count($members) === count($returns), 'Unexpected PostgreSQL index selector method count');
                foreach ($members as $member) {
                    requireShape($member->isPublic() && !$member->isStatic() && $member->returnType !== null
                        && types($member->returnType, $prefix, $short) === ($returns[$member->name->toString()] ?? []),
                        'Unexpected PostgreSQL index selector publication');
                }
            }
            if ($dialect === 'postgres' && in_array($short, ['Schema\\Index\\ReindexIndexLowering', 'Schema\\Index\\Scope\\ReindexSchemaLowering', 'Schema\\Index\\ReindexTableLowering'], true)) {
                $members = $class->getMethods();
                $results = $short === 'Schema\\Index\\ReindexTableLowering'
                    ? ['Schema\\Index\\Partition\\ReindexPartitionTable', 'Schema\\Index\\ReindexTable']
                    : [substr($short, 0, -strlen('Lowering'))];
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === $results,
                    'Unexpected PostgreSQL index rebuild publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\Placement\\IndexTablespaceLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'resolve' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['?Schema\\Index\\Placement\\IndexTablespace'],
                    'Unexpected PostgreSQL index placement route');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Index\\Scope\\ReindexDatabaseLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['Schema\\Index\\Scope\\ReindexDatabase', 'Schema\\Index\\Scope\\ReindexSystemCatalogs'],
                    'Unexpected PostgreSQL database rebuild publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\DropRelationLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['Schema\\DropTable', 'Schema\\Index\\DropIndex', 'Schema\\Namespace\\DropSchema',
                        'Schema\\Sequence\\DropSequence', 'Schema\\View\\DropView'],
                    'Unexpected PostgreSQL relation removal publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Alter\\RenameLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\Alter\\RenameColumn', 'Schema\\Alter\\RenameTable', 'Schema\\Sequence\\Alter\\RenameSequence',
                    ],
                    'Unexpected PostgreSQL rename publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Alter\\AlterMixedIndexesLowering') {
                $members = $class->getMethods();
                requireShape(array_map(static fn ($method) => $method->name->toString(), $members) === ['changes', 'lower', 'unmoved']
                    && $members[1]->isPublic() && !$members[1]->isStatic() && $members[1]->returnType !== null
                    && types($members[1]->returnType, $prefix, $short) === ['Schema\\Index\\Change\\AlterIndexes'],
                    'Unexpected MySQL mixed index publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Alter\\AlterIndexRenameLowering') {
                $members = $class->getMethods();
                requireShape(array_map(static fn ($method) => $method->name->toString(), $members) === [
                    'moves', 'lower', 'qualifier', 'unmoved',
                ] && $members[1]->isPublic() && !$members[1]->isStatic() && $members[1]->returnType !== null
                    && types($members[1]->returnType, $prefix, $short) === ['Schema\\Index\\Rename\\RenameIndexes'],
                    'Unexpected MySQL index rename publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Index\\Visibility\\IndexVisibilityLowering') {
                $members = $class->getMethods();
                requireShape(array_map(static fn ($method) => $method->name->toString(), $members) === ['edits', 'lower', 'edit']
                    && $members[1]->isPublic() && !$members[1]->isStatic() && $members[1]->returnType !== null
                    && types($members[1]->returnType, $prefix, $short) === ['Schema\\Index\\Visibility\\SetIndexVisibility'],
                    'Unexpected MySQL index visibility publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Alter\\AlterIndexRemovalLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 2 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'removals' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['?array']
                    && $members[1]->isPublic() && !$members[1]->isStatic()
                    && $members[1]->name->toString() === 'lower' && $members[1]->returnType !== null
                    && types($members[1]->returnType, $prefix, $short) === [
                        'Schema\\Index\\DropIndex', 'Schema\\Index\\DropIndexes',
                    ], 'Unexpected MySQL index removal publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Alter\\RelationChangeLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 2 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'applies' && types($members[0]->returnType, $prefix, $short) === ['bool']
                    && $members[1]->isPublic() && !$members[1]->isStatic()
                    && $members[1]->name->toString() === 'lower' && $members[1]->returnType !== null
                    && types($members[1]->returnType, $prefix, $short) === [
                        'Schema\\Alter\\RenameTable', 'Schema\\DropTable', 'Schema\\Index\\Change\\AlterIndexes',
                        'Schema\\Index\\DropIndex', 'Schema\\Index\\DropIndexes', 'Schema\\Index\\Rename\\RenameIndexes',
                        'Schema\\Index\\Visibility\\SetIndexVisibility', 'Schema\\Truncate\\TruncateTable', 'Schema\\View\\DropView',
                    ], 'Unexpected MySQL relation change publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Alter\\RelationChangeLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\Alter\\RenameColumn', 'Schema\\Alter\\RenameTable', 'Schema\\DropTable', 'Schema\\Index\\DropIndex',
                        'Schema\\Index\\Partition\\ReindexPartitionTable', 'Schema\\Index\\ReindexIndex', 'Schema\\Index\\ReindexTable',
                        'Schema\\Index\\Scope\\ReindexDatabase', 'Schema\\Index\\Scope\\ReindexSchema', 'Schema\\Index\\Scope\\ReindexSystemCatalogs',
                        'Schema\\Namespace\\ChangeSchemaOwner', 'Schema\\Namespace\\DropSchema', 'Schema\\Namespace\\RenameSchema',
                        'Schema\\Sequence\\Access\\GrantSequenceAccess', 'Schema\\Sequence\\Access\\RevokeSequenceAccess',
                        'Schema\\Sequence\\Alter\\MoveSequence', 'Schema\\Sequence\\Alter\\ReconfigureSequence', 'Schema\\Sequence\\Alter\\RenameSequence',
                        'Schema\\Sequence\\Alter\\SetSequenceLogging', 'Schema\\Sequence\\Authorization\\AlterSequenceAuthorization',
                        'Schema\\Sequence\\Defaults\\GrantSequenceDefaults', 'Schema\\Sequence\\Defaults\\RevokeSequenceDefaults',
                        'Schema\\Sequence\\DropSequence', 'Schema\\Truncate\\TruncateTable', 'Schema\\View\\DropView',
                    ], 'Unexpected PostgreSQL relation change publication');
            }
            if ($short === 'Schema\\RelationDeclarationLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\CreateTable', 'Schema\\Index\\Btree\\CreateIndex', 'Schema\\Namespace\\CreateSchema', 'Schema\\Query\\CreateTableAs',
                        'Schema\\Sequence\\CreateSequence', 'Schema\\View\\CreateMaterializedView', 'Schema\\View\\CreateView',
                    ], 'Unexpected PostgreSQL relation and index declaration publication');
            }
            if ($short === 'Schema\\CreateRelationLowering') {
                $members = $class->getMethods();
                $body = 'return match (true) {'
                    . '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable && $statement->query !== null => (new \\'
                    . $prefix . 'Schema\\Query\\CreateTableAsLowering())->lower($operation, $statement),'
                    . '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable => (new \\'
                    . $prefix . 'Schema\\CreateTableLowering())->lower($operation, $statement),'
                    . '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTableLike => (new \\'
                    . $prefix . 'Schema\\Like\\CreateTableLikeLowering())->lower($operation, $statement),'
                    . '$statement instanceof \\' . $platform . '\\Statement\\View\\CreateView => (new \\'
                    . $prefix . 'Schema\\View\\CreateViewLowering())->lower($operation, $statement),};';
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\CreateTable', 'Schema\\Like\\CreateTableLike', 'Schema\\Query\\CreateTableAs', 'Schema\\View\\CreateView',
                    ]
                    && $printer->prettyPrint($members[0]->stmts) === $printer->prettyPrint($parser->parse('<?php ' . $body)),
                    'Unexpected MySQL declaration publication');
            }
            if ($short === 'Schema\\CreateColumnStorageLowering') {
                $methodsByName = $class->getMethods();
                requireShape(
                    count($methodsByName) === 1 && $methodsByName[0]->isPublic() && $methodsByName[0]->name->toString() === 'lower'
                    && types($methodsByName[0]->returnType, $prefix, $short) === ['Schema\\CharacterStorage', 'Schema\\IntegerStorage'],
                    'Unexpected MySQL declaration storage lowering shape'
                );
            }
            if ($dialect === 'mysql' && $short === 'Schema\\LiteralDefaultLowering') {
                $methodsByName = $class->getMethods();
                requireShape(
                    count($methodsByName) === 1 && $methodsByName[0]->isPublic() && $methodsByName[0]->name->toString() === 'lower'
                    && types($methodsByName[0]->returnType, $prefix, $short) === ['IntegerLiteral', 'Literal\\TextLiteral', 'NullLiteral', 'Predicate\\RangeIntegerLiteral'],
                    'Unexpected MySQL declaration default lowering shape'
                );
            }
            if ($dialect === 'sqlite' && $short === 'Schema\\DropLowering') {
                $methodsByName = array_values(array_filter($class->getMethods(), static fn ($method) => $method->isPublic()));
                requireShape(
                    count($methodsByName) === 1 && $methodsByName[0]->name->toString() === 'lower'
                    && types($methodsByName[0]->returnType, $prefix, $short) === ['Schema\\DropTable', 'Schema\\DropView', 'Schema\\Index\\DropIndex', 'Schema\\Trigger\\DropTrigger'],
                    'Unexpected SQLite relation removal publication shape'
                );
            }
            if ($loweringRole !== null) {
                $publicMethods = array_values(array_map(
                    static fn ($method) => $method->name->toString(),
                    array_filter($class->getMethods(), static fn ($method) => $method->isPublic()),
                ));
                $expectedMethods = match ($short) {
                    'Aggregation\\GroupedLowering' => ['applies', 'lower'],
                    'Schema\\Index\\DropIndexLowering' => $dialect === 'mysql' ? ['lower', 'identity', 'index'] : ['lower'],
                    'Schema\\Truncate\\TruncateLowering' => $dialect === 'mysql' ? ['lower', 'referrers'] : ['lower'],
                    'Rows\\With\\WithLowering' => $dialect === 'mysql' ? ['lower', 'query'] : ['lower', 'query', 'read'],
                    default => ['lower'],
                };
                requireShape($publicMethods === $expectedMethods, "Unexpected lowering entry point in $short");
                requireShape($class->getMethod('lower')?->returnType?->toString() === $prefix . $loweringRole, "Lowering return role differs in $short");
            }
        }
        if ($short === 'SelectSql') {
            $checkedDispatch['expression'] = checkedScalarDispatch($class, 'expression', $prefix);
            if ($dialect !== 'sqlite') {
                $checkedDispatch['predicate'] = checkedScalarDispatch($class, 'predicate', $prefix, $checkedDispatch['expression']);
            }
        }
        array_push($checkedBoundaries, ...checkNativeBoundaries(
            $class,
            str_replace('\\', '/', $short),
            $platform,
            $dialect,
            $boundaries,
            $parser,
            $traverser,
            $printer,
        ));
        if (in_array($short, $internal, true) && !in_array($short, $pureCollaborators, true)) {
            continue;
        }
        foreach ($finder->findInstanceOf($ast, Name\FullyQualified::class) as $name) {
            requireShape(in_array($name->toString(), $allowed, true)
                || ($dialect === 'mysql' && in_array($short, ['Schema\\Index\\CreateIndex', 'Schema\\Index\\IndexIdentity', 'Schema\\Index\\IndexNamespace', 'Schema\\Index\\IndexAutoIncrement',
                    'Schema\\Index\\Batch\\IndexRemovalSet', 'Schema\\Index\\Batch\\AutoIncrementRemovalSet',
                    'Schema\\Index\\Dependency\\IndexDependencyClosure', 'Schema\\Index\\Dependency\\IndexForeignKeyRequirement',
                    'Schema\\Index\\Visibility\\IndexVisibilityEdit',
                    'Schema\\Index\\Rename\\IndexRename', 'Schema\\Index\\Rename\\IndexRenameNamespace',
                    'Schema\\Index\\Change\\MixedIndexNamespace'], true)
                    && $name->toString() === $platform . '\\Model\\Statement\\Identifier\\NativeIdentifierCase')
                || ($dialect === 'sqlite' && $short === 'Expression\\CastTypeName' && $name->toString() === $platform . '\\Model\\Contract\\TypeNameAffinity')
                || ($dialect === 'postgres' && in_array($short, ['Schema\\Namespace\\CreateSchema', 'Schema\\Namespace\\ChangeSchemaOwner',
                    'Schema\\Sequence\\Authorization\\SequencePrincipal'], true)
                    && $name->toString() === $platform . '\\Model\\Contract\\StockRoles')
                || ($dialect === 'postgres' && in_array($short, ['Schema\\Index\\IndexReference', 'Schema\\Index\\ConstraintIndexReference', 'Schema\\Index\\ExclusionIndexReference',
                    'Schema\\Index\\MissingIndex', 'Schema\\Index\\Btree\\CreateIndex', 'Schema\\Namespace\\SchemaDropTarget',
                    'Schema\\Namespace\\ChangeSchemaOwner', 'Schema\\Namespace\\RenameSchema', 'Schema\\Sequence\\SequenceName',
                    'Schema\\Sequence\\SequenceSelector', 'Schema\\Sequence\\Ownership\\SequenceOwner', 'Schema\\Sequence\\Alter\\MoveSequence',
                    'Schema\\Sequence\\Access\\SequencesInSchemas', 'Schema\\Sequence\\Defaults\\SequenceDefaultPolicy',
                    'Schema\\Index\\Scope\\ReindexSchema', 'Schema\\Index\\ReindexTableTarget',
                    'Schema\\Index\\Partition\\PartitionIndexReference', 'Schema\\Index\\Partition\\ReindexPartitionTable'], true)
                    && $name->toString() === $platform . '\\Model\\Contract\\Index\\IndexSchemas')
                || ($dialect === 'postgres' && in_array($short, ['Schema\\Index\\DropIndex', 'Schema\\Sequence\\SequenceSelector'], true)
                    && in_array($name->toString(), [
                    $platform . '\\Model\\Contract\\Index\\StockIndexNames',
                    $platform . '\\Model\\Statement\\Identifier\\Name',
                ], true)),
                "Unexpected dependency in $short: $name");
        }
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            requireShape(
                $call->name instanceof Name && (in_array($call->name->toString(), $pureFunctions, true)
                    || ($dialect === 'sqlite' && $short === 'Schema\\Alter\\RenameColumn' && in_array($call->name->toString(), ['in_array', 'strtr'], true))
                    || ($dialect === 'sqlite' && $short === 'Connection\\AttachmentName' && $call->name->toString() === 'explode')
                    || ($dialect === 'sqlite' && in_array($short, ['Schema\\Trigger\\TriggerChecks', 'Schema\\Trigger\\TriggerDestination', 'Schema\\Trigger\\TriggerInsert', 'Schema\\Trigger\\TriggerUpdateName', 'Schema\\Trigger\\TriggerSql'], true)
                        && in_array($call->name->toString(), ['in_array', 'is_int', 'strtoupper'], true))
                    || ($dialect === 'sqlite' && $short === 'Schema\\Trigger\\Mutation\\TriggerUpdate' && $call->name->toString() === 'in_array')
                    || ($dialect === 'sqlite' && $short === 'Schema\\Trigger\\Mutation\\TriggerMutationSql' && $call->name->toString() === 'strtoupper')
                    || ($dialect === 'postgres' && $short === 'Schema\\Key\\PrimaryKeyName' && in_array($call->name->toString(), ['min', 'ord', 'substr'], true))
                    || ($dialect === 'postgres' && $short === 'Schema\\Key\\UniqueKeyName' && in_array($call->name->toString(), ['min', 'max', 'ord', 'substr'], true))
                    || ($dialect === 'postgres' && $short === 'Schema\\Key\\TableKeySet' && $call->name->toString() === 'array_values')
                    || ($dialect === 'postgres' && $short === 'Schema\\Index\\DropIndex' && $call->name->toString() === 'array_intersect_key')
                    || ($dialect === 'postgres' && $short === 'Schema\\Alter\\RenameColumn' && $call->name->toString() === 'in_array')
                    || ($dialect === 'mysql' && $short === 'TableSource' && $call->name->toString() === 'array_fill')
                    || ($dialect === 'mysql' && $short === 'Literal\\TextLiteral' && $call->name->toString() === 'strtr')
                    || ($dialect === 'mysql' && $short === 'Schema\\CreateTable' && $call->name->toString() === 'intdiv')
                    || ($dialect === 'mysql' && $short === 'Schema\\Query\\CreateAsRecord' && in_array($call->name->toString(), ['intdiv', 'strlen'], true))
                    || ($dialect === 'mysql' && $short === 'Schema\\Storage\\InnoDbRecord' && in_array($call->name->toString(), ['min', 'intdiv'], true))
                    || ($dialect === 'mysql' && $short === 'Schema\\Alter\\RenameTable' && $call->name->toString() === 'array_key_exists')
                    || ($dialect === 'mysql' && $short === 'Schema\\Index\\Batch\\AutoIncrementRemovalSet'
                        && in_array($call->name->toString(), ['ksort', 'array_diff_key'], true))
                    || ($dialect === 'postgres' && $short === 'SelectBounds' && in_array($call->name->toString(), ['array_keys', 'max'], true))
                    || ($dialect === 'postgres' && in_array($short, ['Aggregation\\GroupedSelect', 'Ordering\\Order', 'Ordering\\ResultSql', 'Select'], true)
                        && $call->name->toString() === 'is_int')
                    || ($dialect === 'postgres' && $short === 'From\\DerivedSource' && $call->name->toString() === 'array_keys')
                    || ($dialect === 'postgres' && $short === 'SelectSql' && $call->name->toString() === 'sprintf')
                    || ($dialect === 'postgres' && $short === 'Schema\\Index\\Btree\\IndexCondition'
                        && in_array($call->name->toString(), ['array_pop', 'is_string'], true))),
                "Unexpected function in $short",
            );
        }
        $danger = $finder->findFirst($ast, static fn ($node) => $node instanceof Expr\Eval_ || $node instanceof Expr\Include_
            || $node instanceof Expr\ShellExec || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction
            || $node instanceof Expr\AssignRef || $node instanceof PhpParser\Node\ArrayItem && $node->byRef || $node instanceof Expr\Print_ || $node instanceof Expr\Exit_ || $node instanceof Stmt\Echo_
            || $node instanceof Stmt\Global_ || $node instanceof Stmt\Static_
            || $node instanceof Stmt\Foreach_ && $node->byRef
            || $node instanceof PhpParser\Node\Param && ($node->byRef || $node->variadic)
            || $node instanceof PhpParser\Node\Arg && ($node->byRef || $node->unpack)
            || $node instanceof Stmt\ClassMethod && $node->byRef
            || $node instanceof PhpParser\Node\PropertyHook
            || $node instanceof PhpParser\Node\MatchArm && $node->conds === null
            || $node instanceof Expr\Variable && (!is_string($node->name) || str_starts_with($node->name, '_') || $node->name === 'GLOBALS')
            || $node instanceof Expr\New_ && !$node->class instanceof Name
            || $node instanceof Expr\StaticCall && (!$node->class instanceof Name || !$node->name instanceof Identifier)
            || $node instanceof Expr\MethodCall && !$node->name instanceof Identifier
            || $node instanceof Expr\PropertyFetch && !$node->name instanceof Identifier);
        requireShape($danger === null, "Unexpected dynamic PHP in $short");
        if (!isset($properties[$short])) {
            requireShape($class instanceof Class_ && $class->isFinal() && $class->getProperties() === [], "Stateful SELECT collaborator $short");
            continue;
        }
        $actual = [];
        foreach ($class->getProperties() as $property) {
            requireShape($property->isReadonly() && !$property->isStatic() && $property->type !== null, "Writable or untyped property in $short");
            foreach ($property->props as $prop) {
                $actual[$prop->name->toString()] = types($property->type, $prefix, $short);
            }
        }
        $public = [];
        foreach ($class->getMethods() as $method) {
            requireShape($method->isPublic() && !$method->isStatic(), "Unexpected method in $short");
            $public[] = $method->name->toString();
            if ($method->name->toString() !== '__construct') {
                continue;
            }
            foreach ($method->params as $param) {
                if ($param->flags !== 0) {
                    requireShape(($param->flags & Class_::MODIFIER_READONLY) !== 0, "Writable promotion in $short");
                    $actual[$param->var->name] = types($param->type, $prefix, $short);
                }
            }
        }
        ksort($actual);
        $expected = $properties[$short];
        ksort($expected);
        requireShape($actual === $expected, "Property domain differs in $short");
        requireShape($methods[$short] === $public, "Publication paths differ in $short");
        if (isset($enumCases[$short])) {
            requireShape($class instanceof Enum_ && $class->scalarType?->toString() === 'string', 'Arithmetic operators must be a string enum');
            $operators = [];
            foreach ($class->stmts as $case) {
                if ($case instanceof Stmt\ClassMethod && (
                    ($dialect === 'mysql' && in_array($short, ['Schema\\IntegerStorage', 'Schema\\Index\\IndexRole', 'From\\ExternalRelationKind', 'From\\ColumnKind'], true))
                    || ($dialect === 'postgres' && $short === 'Schema\\Sequence\\SequencePersistence')
                )) {
                    continue;
                }
                requireShape($case instanceof Stmt\EnumCase && $case->expr instanceof String_, 'An arithmetic operator must be a literal case');
                $operators[$case->name->toString()] = $case->expr->value;
            }
            requireShape($operators === $enumCases[$short], "Operator meanings differ in $short");
            $checkedValues[$short] = ['kind' => 'enum', 'fields' => (object) $actual, 'lists' => (object) []];
            continue;
        }
        requireShape($class instanceof Class_ && $class->isFinal() && $class->extends === null, "Open value class $short");
        requireShape(array_map(static fn ($name) => $name->toString(), $class->implements) === (in_array($short, $roots, true) ? ['SqlSemantics\\SemanticsIR'] : []), "Interfaces differ in $short");
        $traits = $finder->findInstanceOf($class->stmts, TraitUse::class);
        requireShape(count($traits) === 1 && count($traits[0]->traits) === 1 && $traits[0]->traits[0]->toString() === $snapshot && $traits[0]->adaptations === [], "Snapshot paths differ in $short");
        $role = str_replace('\\', '/', $short);
        $arrays = $storage[$role] ?? [];
        checkValueStorage($class, $expected, $arrays, $short, $prefix, $platform . '\\Model\\Diagnostic\\Check', $parser, $printer, $finder, $checkedBoundaries);
        $checkedValues[$short] = ['kind' => 'final-readonly-fields', 'fields' => (object) $actual, 'lists' => (object) $arrays];
        if ($arrays !== []) {
            $listFields[$role] = array_keys($arrays);
        }
    }
    requireShape(array_diff([...array_keys($properties), ...$internal], $observed) === [] && count($observed) === count($properties) + count($internal), 'Closed IR inventory differs');
    sort($observed);
    ksort($listFields);
    ksort($checkedValues);
    requireShape(array_keys($listFields) === array_keys($storage), 'List storage catalogue is incomplete');
    sort($checkedBoundaries);
    $expectedBoundaries = array_keys($boundaries);
    sort($expectedBoundaries);
    requireShape($checkedBoundaries === $expectedBoundaries, 'Native boundary coverage differs');
    foreach ($deferredBindings as [$file, $expected, $message]) {
        requireShape(is_file($file) && hash_file('sha256', $file) === $expected, $message);
    }
    echo json_encode(['status' => 'verified', 'types' => $observed, 'checkedLists' => $listFields, 'checkedBoundaries' => $checkedBoundaries,
        'checkedBindingSources' => (object) $checkedBindingSources,
        'checkedBindingDependencies' => (object) $checkedBindingDependencies, 'checkedValues' => (object) $checkedValues, 'checkedDispatch' => $checkedDispatch]) . "\n";
} catch (RuntimeException $failure) {
    echo json_encode(['status' => 'shape-mismatch', 'message' => $failure->getMessage()]) . "\n";
    exit(1);
}
