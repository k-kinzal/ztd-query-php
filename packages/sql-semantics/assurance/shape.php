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
    $properties['From\\ColumnKind'] = $methods['From\\ColumnKind'] = [];
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
    $enumCases['From\\ColumnKind'] = ['SignedInt' => 'int'];
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
        array_push($internal, 'Schema\\PersistentDeclarationLowering', 'Schema\\View\\CreateMaterializedViewLowering',
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
        $identity = 'Schema\\Identity\\TableIdentity';
        $properties[$identity] = ['name' => [$tableName], 'kind' => ['From\\ExternalRelationKind']];
        $methods[$identity] = ['__construct', 'quoted'];
        $copy = 'Schema\\Like\\CreateTableLike';
        $roots[] = $copy;
        $internal[] = 'Schema\\Like\\CreateTableLikeLowering';
        $properties[$copy] = ['destination' => [$identity], 'source' => [$identity], 'ifNotExists' => ['bool'],
            'release' => [$platform . '\\Model\\Contract\\GrammarRelease']];
        $methods[$copy] = ['__construct', 'profileId', 'toString'];
        $createAs = 'Schema\\Query\\CreateTableAs';
        $roots[] = $createAs;
        array_push($internal, 'Schema\\Query\\CreateTableAsLowering', 'Schema\\Query\\CreateAsLayout');
        $pureCollaborators[] = 'Schema\\Query\\CreateAsLayout';
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
        array_push($internal, 'Schema\\Key\\CreatePrimaryKeyLowering', 'Schema\\CreateTableLayoutLowering', 'Schema\\Key\\PrimaryColumnPolicy');
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
        . '$this->catalog = new \\' . $platform . '\\Model\\Facade\\Catalog($this->profile, $searchPath?->schemas, $this->parser);'
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
    $analyze = $resolve . ' $operation = ' . $bind . '; $statement = $operation->statement; return match (true) {'
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
        . ($dialect === 'mysql'
            ? '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\CreateTableLike, $statement instanceof \\' . $platform
                . '\\Statement\\View\\CreateView => (new \\' . $prefix . 'Schema\\CreateRelationLowering())->lower($operation, $statement),'
            : '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\CreateTableAs, $statement instanceof \\' . $platform . '\\Statement\\Table\\View\\CreateView, $statement instanceof \\' . $platform
                . '\\Statement\\Table\\View\\CreateMaterializedView => (new \\'
                . $prefix . 'Schema\\PersistentDeclarationLowering())->lower($operation, $statement),')
        . ($dialect === 'postgres' ? '$statement instanceof \\' . $platform . '\\Statement\\Table\\View\\RefreshMaterializedView => (new \\'
            . $prefix . 'Schema\\View\\RefreshMaterializedViewLowering())->lower($operation, $statement),' : '')
        . ($dialect === 'postgres'
            ? '$statement instanceof \\' . $platform . '\\Statement\\Object\\Drop, $statement instanceof \\' . $platform . '\\Statement\\Object\\Rename, $statement instanceof \\'
                . $platform . '\\Statement\\Table\\TruncateTable'
                . ' => (new \\' . $prefix . 'Schema\\Alter\\RelationChangeLowering())->lower($operation, $statement),'
            : '$statement instanceof \\' . $platform . '\\Statement\\Alter\\DropTable, $statement instanceof \\' . $platform
                . '\\Statement\\View\\DropView, $statement instanceof \\' . $platform . '\\Statement\\Alter\\RenameTable'
                . ', $statement instanceof \\' . $platform . '\\Statement\\Alter\\TruncateTable'
                . ' => (new \\' . $prefix . 'Schema\\Alter\\RelationChangeLowering())->lower($operation, $statement),')
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
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateTable => (new \\' . $prefix . 'Schema\\CreateTableLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateIndex => (new \\' . $prefix . 'Schema\\Index\\CreateIndexLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateTableAs => (new \\' . $prefix . 'Schema\\Query\\CreateTableAsLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateView => (new \\' . $prefix . 'Schema\\View\\CreateViewLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterRenameColumn, $statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterRenameTable => (new \\' . $prefix . 'Schema\\Alter\\RenameLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterAddColumn => (new \\' . $prefix . 'Schema\\Alter\\AddColumnLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\AlterDropColumn => (new \\' . $prefix . 'Schema\\Alter\\DropColumnLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Analyze,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Reindex,'
            . '$statement instanceof \\' . $platform . '\\Statement\\Maintenance\\Pragma => (new \\' . $prefix . 'Maintenance\\MaintenanceLowering())->lower($operation, $statement),'
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
            ['sqlite-drop-column-binding-v4.json', '61cf124acd1c6c4a5a923f6721fdaf701d63b3a5f4f2665c2a8a3a643885f15c', 'Column removal binding'],
            ['sqlite-drop-index-binding-v3.json', 'e96dccf114b72f782c4c7eb8bf108629c442393a73689c008eb1fd09bce55d96', 'Index removal binding'],
            ['sqlite-constraint-index-binding-v1.json', '792ea111ed9e254c8f883e33ee6b33b2afa7d9fdd2317575e15864ae935ae63e', 'Constraint index binding'],
            ['sqlite-reindex-binding-v2.json', '03e65c0bf4b2ca33a6ab529d9385de4d0ea7c3c4a325a4dc1e2f49fd7efbcdb2', 'Reindex binding'],
            ['sqlite-analyze-statistics-binding-v2.json', 'd142c29969ec426d0bd2518c5acc4b0ae38bdba4f2c7c298690f89bd3dcbaa0b', 'Analyze statistics binding'],
            ['sqlite-flag-pragmas-binding-v1.json', 'b5636898d08476b16da1d450e4555f480008addc4ec7f44aefa90b51eb83eeb8', 'Flag pragma binding'],
            ['sqlite-folded-binding-sources-v8.json', '96f3f69296855ca2cf8b6047b4026cf8d9c6688c4dd94a8cb86139f5e20ef350', 'Folded binding'],
            ['sqlite-merged-join-binding-v3.json', '08154530910f68b6a551b2278a048541c0ed919e557f00af01e6f34f6da2e6c4', 'Merged join binding'],
        ],
        'postgres' => [
            ['postgres-join-binding-v3.json', '5195458168a04370df438fcebfc8b81aa3877fed56f16f72c256ccac96bf0509', 'PostgreSQL join binding'],
            ['postgres-declaration-binding-sources-v1.json', '98302acdf9adbff1aa2619e30a190de6007ecfe826f5cf70c09407733d51b5b0', 'PostgreSQL declaration binding'],
            ['postgres-integer-binding-sources-v1.json', '108d4ad1de0b83709687beaa2c9f405e4f2accae0b5c19d275b6eaf40dd9f738', 'Integer binding'],
            ['postgres-output-order-binding-v1.json', 'e3bbcfd8480341a01b817401a6612bd642640ddc727dca066fdc12ce05e9d1e7', 'PostgreSQL output order binding'],
        ],
        'mysql' => [
            ['mysql-from-binding-v2.json', 'd46f12a3f933d01aa286fc9ccf2ece3aae09713a6cfe22b86ca861d288460741', 'MySQL FROM binding'],
            ['mysql-text-literal-binding-v1.json', '9ae75f8c5ee63d23788c813d0552cc10fb12096133d4c76cc93321508a6f7329', 'MySQL text binding'],
            ['mysql-varchar-binding-v5.json', '1039329131e0b89f6eab37a54962abb072739fc96b62851488ca39c2d07c358a', 'MySQL VARCHAR binding'],
            ['mysql-ordered-table-rename-binding-v4.json', '4b99341a225f348f40a39e89cd0c870e87b6415722c2a855dda8473d17b166f2', 'MySQL ordered rename binding'],
            ['mysql-session-table-binding-v3.json', 'f9c7f548cb0d727552417e2ebed47e503fe7c6c53181bb70fdba067269ce9692', 'MySQL session table binding'],
            ['mysql-drop-view-binding-v4.json', 'e9c6e4dd82ac7551a716f5a0924dd325a76b82b75eb0b899d07d758f7fdeb5ee', 'MySQL view removal binding'],
            ['mysql-create-primary-binding-v1.json', '83770e7c7605a81930b1d924dd3090cebcdb754788b3302791e04948c99fe7b3', 'MySQL primary key binding'],
        ],
    };
    $providedBindings = [];
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
            requireShape(is_file($file) && hash_file('sha256', $file) === $approved['sha256'], "$label source differs in $path");
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
    $storagePath = __DIR__ . '/value-storage-v52.json';
    requireShape(
        hash_file('sha256', $storagePath) === '6cdf01c362f749327433b58ccf4f3b8e5baaa5ff9f625dc4be5eb8b9713214a3',
        'Value storage contract differs from the recorded revision'
    );
    $storage = json_decode(file_get_contents($storagePath), true, 512, JSON_THROW_ON_ERROR)['dialects'][$dialect];
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
                'Schema\\Index\\DropIndexLowering' => 'Schema\\Index\\DropIndex',
                'Maintenance\\ReindexLowering' => 'Maintenance\\Reindex',
                'Maintenance\\AnalyzeLowering' => 'Maintenance\\Analyze',
                'Maintenance\\FlagPragmaLowering' => 'Maintenance\\FlagPragma',
                'Schema\\View\\DropViewLowering' => 'Schema\\View\\DropView',
                'Transaction\\TransactionLowering' => 'Transaction\\Transaction',
                default => null,
            };
            if ($short === 'Maintenance\\MaintenanceLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Maintenance\\Analyze', 'Maintenance\\FlagPragma', 'Maintenance\\Reindex',
                    ], 'Unexpected SQLite maintenance publication');
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
            if ($dialect === 'postgres' && $short === 'Schema\\DropRelationLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['Schema\\DropTable', 'Schema\\View\\DropView'],
                    'Unexpected PostgreSQL relation removal publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Alter\\RenameLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === ['Schema\\Alter\\RenameColumn', 'Schema\\Alter\\RenameTable'],
                    'Unexpected PostgreSQL rename publication');
            }
            if ($dialect === 'mysql' && $short === 'Schema\\Alter\\RelationChangeLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\Alter\\RenameTable', 'Schema\\DropTable', 'Schema\\Truncate\\TruncateTable', 'Schema\\View\\DropView',
                    ], 'Unexpected MySQL relation change publication');
            }
            if ($dialect === 'postgres' && $short === 'Schema\\Alter\\RelationChangeLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\Alter\\RenameColumn', 'Schema\\Alter\\RenameTable', 'Schema\\DropTable',
                        'Schema\\Truncate\\TruncateTable', 'Schema\\View\\DropView',
                    ], 'Unexpected PostgreSQL relation change publication');
            }
            if ($short === 'Schema\\PersistentDeclarationLowering') {
                $members = $class->getMethods();
                requireShape(count($members) === 1 && $members[0]->isPublic() && !$members[0]->isStatic()
                    && $members[0]->name->toString() === 'lower' && $members[0]->returnType !== null
                    && types($members[0]->returnType, $prefix, $short) === [
                        'Schema\\CreateTable', 'Schema\\Query\\CreateTableAs', 'Schema\\View\\CreateMaterializedView', 'Schema\\View\\CreateView',
                    ], 'Unexpected PostgreSQL persistent declaration publication');
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
                    && types($methodsByName[0]->returnType, $prefix, $short) === ['Schema\\DropTable', 'Schema\\DropView', 'Schema\\Index\\DropIndex'],
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
                || ($dialect === 'sqlite' && $short === 'Expression\\CastTypeName' && $name->toString() === $platform . '\\Model\\Contract\\TypeNameAffinity'),
                "Unexpected dependency in $short: $name");
        }
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            requireShape(
                $call->name instanceof Name && (in_array($call->name->toString(), $pureFunctions, true)
                    || ($dialect === 'sqlite' && $short === 'Schema\\Alter\\RenameColumn' && in_array($call->name->toString(), ['in_array', 'strtr'], true))
                    || ($dialect === 'postgres' && $short === 'Schema\\Alter\\RenameColumn' && $call->name->toString() === 'in_array')
                    || ($dialect === 'mysql' && $short === 'TableSource' && $call->name->toString() === 'array_fill')
                    || ($dialect === 'mysql' && $short === 'Literal\\TextLiteral' && $call->name->toString() === 'strtr')
                    || ($dialect === 'mysql' && in_array($short, ['Schema\\CreateTable', 'Schema\\Query\\CreateAsLayout'], true)
                        && $call->name->toString() === 'intdiv')
                    || ($dialect === 'mysql' && $short === 'Schema\\Alter\\RenameTable' && $call->name->toString() === 'array_key_exists')
                    || ($dialect === 'postgres' && $short === 'SelectBounds' && in_array($call->name->toString(), ['array_keys', 'max'], true))
                    || ($dialect === 'postgres' && in_array($short, ['Aggregation\\GroupedSelect', 'Ordering\\Order', 'Ordering\\ResultSql', 'Select'], true)
                        && $call->name->toString() === 'is_int')
                    || ($dialect === 'postgres' && $short === 'From\\DerivedSource' && $call->name->toString() === 'array_keys')
                    || ($dialect === 'postgres' && $short === 'SelectSql' && $call->name->toString() === 'sprintf')),
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
                if ($dialect === 'mysql' && in_array($short, ['Schema\\IntegerStorage', 'From\\ExternalRelationKind'], true) && $case instanceof Stmt\ClassMethod) {
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
    echo json_encode(['status' => 'verified', 'types' => $observed, 'checkedLists' => $listFields, 'checkedBoundaries' => $checkedBoundaries,
        'checkedBindingSources' => (object) $checkedBindingSources,
        'checkedBindingDependencies' => (object) $checkedBindingDependencies, 'checkedValues' => (object) $checkedValues, 'checkedDispatch' => $checkedDispatch]) . "\n";
} catch (RuntimeException $failure) {
    echo json_encode(['status' => 'shape-mismatch', 'message' => $failure->getMessage()]) . "\n";
    exit(1);
}
