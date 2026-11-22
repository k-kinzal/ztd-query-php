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
    array_push($operands, 'Expression\\CaseExpression', 'Expression\\CastExpression', 'Expression\\UnaryExpression', 'Literal\\BlobLiteral', 'Literal\\HexIntegerLiteral', 'Predicate\\BetweenTest', 'Predicate\\Comparison', 'Predicate\\Logical', 'Predicate\\MembershipTest', 'Predicate\\TruthTest', 'Literal\\RealLiteral', 'Literal\\TextLiteral');
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
    $methods['Identifier'] = ['__construct', 'quoted'];
    $properties['TableSource']['only'] = ['bool'];
}
if ($dialect === 'mysql') {
    $properties['IntegerLiteral']['leadingZeroes'] = ['int'];
    $properties['IntegerLiteral']['negativeZero'] = ['bool'];
    $methods['IntegerLiteral'][] = 'toString';
}
$internal = ['ExpressionLowering', 'SelectBounds', 'SelectLowering', 'SelectSql', 'SourceBinding'];
$pureCollaborators = ['SelectBounds', 'SelectSql'];
$roots = ['Select'];
$enumCases = ['ArithmeticOperator' => ['Add' => '+', 'Subtract' => '-', 'Multiply' => '*']];
if ($dialect === 'mysql') {
    $enumCases['ArithmeticOperator'] += ['IntegerDivide' => 'DIV', 'Remainder' => '%'];
}
if ($dialect === 'postgres') {
    $enumCases['ArithmeticOperator'] += ['Divide' => '/', 'Remainder' => '%', 'BitAnd' => '&', 'BitOr' => '|', 'BitXor' => '#'];
}
if ($dialect === 'sqlite') {
    $enumCases['ArithmeticOperator'] += [
        'Divide' => '/', 'Remainder' => '%', 'BitAnd' => '&', 'BitOr' => '|', 'ShiftLeft' => '<<', 'ShiftRight' => '>>',
    ];
    $properties['Expression\\UnaryExpression'] = ['kind' => ['Expression\\UnaryKind'], 'operand' => $operands];
    $methods['Expression\\UnaryExpression'] = ['__construct'];
    $properties['Expression\\UnaryKind'] = $methods['Expression\\UnaryKind'] = [];
    $enumCases['Expression\\UnaryKind'] = ['Plus' => '+', 'Not' => 'NOT', 'Complement' => '~'];
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
    $properties['Aggregation\\GroupCollation'] = $methods['Aggregation\\GroupCollation'] = [];
    $enumCases['Aggregation\\GroupCollation'] = ['Binary' => 'BINARY', 'NoCase' => 'NOCASE', 'RightTrim' => 'RTRIM'];
    $properties['Aggregation\\Count'] = ['argument' => ['?ColumnReference'], 'distinctCollation' => ['?Aggregation\\GroupCollation']];
    $properties['Aggregation\\CountComparison'] = [
        'count' => ['Aggregation\\Count'], 'operator' => ['Predicate\\ComparisonOperator'], 'threshold' => ['IntegerLiteral', 'NullLiteral', 'Parameter'], 'thresholdFirst' => ['bool'],
    ];
    $properties['Aggregation\\GroupKey'] = ['column' => ['ColumnReference'], 'collation' => ['Aggregation\\GroupCollation']];
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
    $properties['Expression\\CastExpression'] = ['target' => ['Expression\\CastTarget'], 'operand' => $operands];
    $methods['Expression\\CastExpression'] = ['__construct'];
    $properties['Expression\\CastTarget'] = $methods['Expression\\CastTarget'] = [];
    $enumCases['Expression\\CastTarget'] = ['Integer' => 'INTEGER', 'Real' => 'REAL', 'Numeric' => 'NUMERIC', 'Text' => 'TEXT', 'Blob' => 'BLOB'];
    $properties['Literal\\BlobLiteral'] = ['hex' => ['string']];
    $properties['Literal\\HexIntegerLiteral'] = ['digits' => ['string'], 'negated' => ['bool']];
    $properties['Literal\\RealLiteral'] = ['whole' => ['string'], 'fraction' => ['?string'], 'exponent' => ['?string'], 'negative' => ['bool']];
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
    $methods['Literal\\RealLiteral'][] = 'toString';
    $properties['Select']['source'] = ['Rows\\LocalSource', 'TableSource', 'null'];
    $properties['From\\Join'] = ['kind' => ['From\\JoinKind'], 'on' => $nullablePredicates, 'source' => ['TableSource']];
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
        'conflict' => ['Mutation\\InsertConflict'], 'returning' => ['array'],
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
    $properties['Schema\\CreateTable'] = ['schema' => ['Identifier'], 'table' => ['Identifier'], 'columns' => ['array'], 'ifNotExists' => ['bool']];
    $methods['Schema\\CreateTable'] = $methods['Select'];
    $properties['Schema\\TableColumn'] = [
        'name' => ['Identifier'], 'type' => ['Schema\\DeclaredDatatypeName'], 'notNull' => ['bool'],
        'default' => ['IntegerLiteral', 'Literal\\BlobLiteral', 'Literal\\HexIntegerLiteral', 'Literal\\RealLiteral',
            'Literal\\TextLiteral', 'NullLiteral', 'null'],
    ];
    array_push($internal, 'Schema\\CreateColumnLowering', 'Schema\\DefaultLowering');
    $methods['Schema\\TableColumn'] = ['__construct', 'toString'];
    $roots[] = 'Mutation\\Delete';
    $roots[] = 'Mutation\\Insert';
    $roots[] = 'Mutation\\Update';
    $properties['Rows\\Compound'] = ['first' => ['Rows\\ValuesQuery', 'Select'], 'limit' => ['?Ordering\\Limit'], 'orderBy' => ['array'], 'steps' => ['array']];
    $properties['Rows\\CompoundStep'] = ['operator' => ['Rows\\CompoundOperator'], 'query' => ['Rows\\ValuesQuery', 'Select']];
    $properties['Rows\\CompoundOperator'] = [];
    $methods['Rows\\Compound'] = $methods['Select'];
    $methods['Rows\\CompoundStep'] = ['__construct'];
    $methods['Rows\\CompoundOperator'] = [];
    $roots[] = 'Rows\\Compound';
    array_push($internal, 'Expression\\BinaryOperatorLowering', 'Literal\\NumericLiteralLowering', 'Rows\\CompoundBounds', 'Rows\\CompoundLowering');
    $pureCollaborators[] = 'Rows\\CompoundBounds';
    $enumCases['Rows\\CompoundOperator'] = ['UnionAll' => 'UNION ALL', 'Union' => 'UNION', 'Intersect' => 'INTERSECT', 'Except' => 'EXCEPT'];
    $roots[] = 'Rows\\ValuesQuery';
    $roots[] = 'Schema\\DropTable';
    $roots[] = 'Schema\\CreateTable';
    $properties['Predicate\\Comparison'] = ['operator' => ['Predicate\\ComparisonOperator'], 'left' => $operands, 'right' => $operands];
    $properties['Predicate\\Logical'] = ['operator' => ['Predicate\\LogicalOperator'], 'left' => $predicates, 'right' => $predicates];
    $enumCases['From\\JoinKind'] = ['Inner' => 'INNER', 'Cross' => 'CROSS', 'Left' => 'LEFT', 'Right' => 'RIGHT', 'Full' => 'FULL'];
    $enumCases['Mutation\\InsertConflict'] = ['Inherit' => 'inherit', 'Abort' => 'abort', 'Ignore' => 'ignore'];
    $enumCases['Mutation\\UpdateConflict'] = ['Inherit' => 'inherit', 'Abort' => 'abort', 'Ignore' => 'ignore'];
    $enumCases['Schema\\DeclaredDatatypeName'] = ['Untyped' => '', 'Integer' => 'INTEGER', 'Text' => 'TEXT', 'Real' => 'REAL', 'Blob' => 'BLOB'];
    $enumCases['Predicate\\ComparisonOperator'] = [
        'Equal' => '=', 'NotEqual' => '<>', 'Less' => '<', 'LessOrEqual' => '<=',
        'Greater' => '>', 'GreaterOrEqual' => '>=', 'Is' => 'IS', 'IsNot' => 'IS NOT',
    ];
    $enumCases['Predicate\\LogicalOperator'] = ['And' => 'AND', 'Or' => 'OR'];
    foreach (['From\\JoinKind', 'Mutation\\InsertConflict', 'Mutation\\UpdateConflict', 'Predicate\\ComparisonOperator', 'Predicate\\LogicalOperator', 'Schema\\DeclaredDatatypeName'] as $enum) {
        $methods[$enum] = $properties[$enum] = [];
    }
    foreach (['From\\Join', 'Ordering\\Order', 'Ordering\\Limit', 'Predicate\\Comparison', 'Predicate\\Logical'] as $value) {
        $methods[$value] = ['__construct'];
    }
    array_push($internal, 'From\\SourceDeclarations', 'From\\QueryClauses', 'Mutation\\DeleteLowering', 'Mutation\\InsertLowering', 'Mutation\\UpdateLowering', 'Predicate\\ExpressionScope', 'Rows\\ValuesLowering', 'Rows\\LocalSourceLowering', 'Schema\\DropTableLowering', 'Schema\\CreateTableLowering');
    $pureCollaborators[] = 'Predicate\\ExpressionScope';
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
        array_push($internal, 'Ordering\\ProjectionLabel', 'Ordering\\ProjectionColumns');
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
    $properties['Aggregation\\GroupColumn'] = ['value' => ['Aggregation\\Aggregate', 'ColumnReference'], 'name' => ['Identifier']];
    $methods['Aggregation\\Aggregate'] = $methods['Aggregation\\GroupColumn'] = ['__construct'];
    array_push($internal, 'Aggregation\\AggregateBinding', 'Aggregation\\GroupedKeys', 'Aggregation\\GroupedLowering', 'Aggregation\\GroupedSql');
    $pureCollaborators[] = 'Aggregation\\GroupedSql';
    $properties['ColumnReference']['occurrence'] = ['int'];
    $truthValues = ['BooleanLiteral', 'NullLiteral', 'Predicate\\IntegerComparison', 'Predicate\\LogicalPredicate', 'Predicate\\NullTest'];
    $conditions = [...$truthValues, 'null'];
    $properties['Aggregation\\GroupedSelect']['where'] = $conditions;
    $properties['Select']['source'] = ['From\\Join', 'TableSource', 'null'];
    $properties['Select']['where'] = $conditions;
    $properties['Select']['distinct'] = ['bool'];
    $properties['Select']['orderBy'] = ['array'];
    $properties['Select']['limit'] = ['?Ordering\\Limit'];
    $properties['Ordering\\Order'] = ['position' => ['int'], 'descending' => ['bool'], 'nullsFirst' => ['bool']];
    $properties['Ordering\\Limit'] = ['count' => [$dialect === 'mysql' ? 'int' : '?int'], 'offset' => ['int']];
    $methods['Ordering\\Order'] = $methods['Ordering\\Limit'] = ['__construct'];
    $properties['From\\Join'] = [
        'kind' => ['From\\JoinKind'], 'left' => ['From\\DerivedSource', 'From\\Join', 'TableSource'],
        'right' => ['From\\DerivedSource', 'From\\Join', 'TableSource'], 'on' => $conditions,
    ];
    $properties['From\\DerivedSource'] = ['definition' => ['Select']];
    $methods['From\\DerivedSource'] = ['__construct'];
    $methods['From\\Join'] = ['__construct', 'inputs'];
    $properties['From\\JoinKind'] = $methods['From\\JoinKind'] = [];
    $enumCases['From\\JoinKind'] = ['Cross' => 'CROSS', 'Inner' => 'INNER', 'Left' => 'LEFT', 'Right' => 'RIGHT'];
    if ($dialect === 'postgres') {
        $enumCases['From\\JoinKind']['Full'] = 'FULL';
    }
    array_push($internal, 'From\\DerivedBinding', 'From\\JoinBinding', 'From\\OccurrenceScope', 'Predicate\\PredicateLowering', 'Ordering\\ResultLowering', 'Ordering\\ResultSql');
    array_push($pureCollaborators, 'From\\OccurrenceScope', 'Ordering\\ResultSql');
    $internal[] = 'Mutation\\DeleteLowering';
    $properties['Mutation\\Delete'] = [
        'target' => ['TableSource'],
        'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
        'where' => $conditions,
    ];
    $properties['Predicate\\IntegerComparison'] = [
        'operator' => ['Predicate\\IntegerOperator'], 'left' => ['ColumnReference', 'IntegerLiteral'], 'right' => ['ColumnReference', 'IntegerLiteral'],
    ];
    $properties['Predicate\\IntegerOperator'] = [];
    $properties['Predicate\\LogicalPredicate'] = [
        'operator' => ['Predicate\\LogicalOperator'], 'left' => $truthValues, 'right' => $conditions,
    ];
    $properties['Predicate\\LogicalOperator'] = $methods['Predicate\\LogicalOperator'] = [];
    $properties['Predicate\\NullTest'] = [
        'operand' => ['BooleanLiteral', 'ColumnReference', 'IntegerLiteral', 'NullLiteral', 'Predicate\\IntegerComparison', 'Predicate\\LogicalPredicate', 'Predicate\\NullTest'],
        'negated' => ['bool'],
    ];
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
    $methods['Mutation\\InsertQuery'] = $methods['Select'];
    $internal[] = 'Mutation\\InsertTargetBinding';
    $methods['Mutation\\Insert'] = $methods['Select'];
    $properties['Mutation\\InsertRow'] = ['values' => ['array']];
    $properties['Mutation\\InsertCell'] = ['value' => ['?IntegerLiteral']];
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
    $methods['Mutation\\UpdateAssignment'] = ['__construct'];
    $internal[] = 'Mutation\\UpdateLowering';
    $properties['Rows\\LocalProjection'] = ['definition' => ['Rows\\LocalProjection', 'Select'], 'columns' => ['array']];
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
    $methods['Rows\\Compound'] = $methods['Select'];
    $properties['Rows\\CompoundOperator'] = $methods['Rows\\CompoundOperator'] = [];
    $properties['Rows\\ResultKind'] = $methods['Rows\\ResultKind'] = [];
    $enumCases['Rows\\CompoundOperator'] = ['Union' => 'UNION', 'Intersect' => 'INTERSECT', 'Except' => 'EXCEPT'];
    $enumCases['Rows\\ResultKind'] = $dialect === 'mysql'
        ? ['Integer' => 'int32', 'WideInteger' => 'int64', 'Unknown' => 'unknown', 'NullOnly' => 'null-only']
        : ['Integer' => 'int32', 'Boolean' => 'bool', 'Unknown' => 'unknown', 'Text' => 'text'];
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
    array_push($internal, 'Schema\\CreateColumnLowering', 'Schema\\CreateTableLowering');
    $properties['Schema\\DropTable'] = [
        'targets' => ['array'], 'ifExists' => ['bool'], 'release' => [$platform . '\\Model\\Contract\\GrammarRelease'],
    ];
    if ($dialect === 'postgres') {
        $properties['Schema\\DropTable']['cascade'] = ['bool'];
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
$pureFunctions = ['strlen','str_contains','str_starts_with','preg_match','str_replace','ltrim','strcmp','count','isset','implode','array_pop','array_push','array_merge','array_is_list','strpbrk','substr_count','str_repeat'];

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
    $drop = $dialect === 'mysql' ? 'Alter\\DropTable' : 'Object\\Drop';
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
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\QueryExpression && $statement->with !== null,'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select && $statement->from instanceof \\' . $platform
        . '\\Statement\\Relation\\DerivedTable => (new \\' . $prefix . 'Rows\\LocalProjectionLowering())->lower($operation),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $compound . ', $statement instanceof \\' . $platform
        . '\\Statement\\Query\\ParenthesizedQuery => (new \\' . $prefix . 'Rows\\CompoundLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\Query\\Select && (new \\' . $prefix . 'Aggregation\\GroupedLowering())->applies($statement) => (new \\' . $prefix . 'Aggregation\\GroupedLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $kind . '\\Delete => (new \\' . $prefix . 'Mutation\\DeleteLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\Table\\CreateTable => (new \\' . $prefix . 'Schema\\CreateTableLowering())->lower($operation, $statement),'
        . '$statement instanceof \\' . $platform . '\\Statement\\' . $drop . ' => (new \\' . $prefix . 'Schema\\DropTableLowering())->lower($operation, $statement),'
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
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\Drop => (new \\' . $prefix . 'Schema\\DropTableLowering())->lower($operation, $statement),'
            . '$statement instanceof \\' . $platform . '\\Statement\\Schema\\CreateTable => (new \\' . $prefix . 'Schema\\CreateTableLowering())->lower($operation, $statement),'
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
    $base = $package . '/src/Ir';
    $storagePath = __DIR__ . '/value-storage-v17.json';
    requireShape(
        hash_file('sha256', $storagePath) === 'f72fc20a87dfbf36cedfefa13be0c7ed8d30a209bf051bfd62f0600f60b9d143',
        'Value storage contract differs from the recorded revision'
    );
    $storage = json_decode(file_get_contents($storagePath), true, 512, JSON_THROW_ON_ERROR)['dialects'][$dialect];
    $listFields = [];
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
                'Rows\\Recursive\\RecursiveSeriesLowering' => 'Rows\\RecursiveSeries',
                'Schema\\CreateTableLowering' => 'Schema\\CreateTable',
                'Schema\\CreateColumnLowering' => 'Schema\\TableColumn',
                'Schema\\DropTableLowering' => 'Schema\\DropTable',
                'Transaction\\TransactionLowering' => 'Transaction\\Transaction',
                default => null,
            };
            if ($loweringRole !== null) {
                $publicMethods = array_values(array_map(
                    static fn ($method) => $method->name->toString(),
                    array_filter($class->getMethods(), static fn ($method) => $method->isPublic()),
                ));
                requireShape($publicMethods === ($short === 'Aggregation\\GroupedLowering' ? ['applies', 'lower'] : ['lower']), "Unexpected lowering entry point in $short");
                requireShape($class->getMethod('lower')?->returnType?->toString() === $prefix . $loweringRole, "Lowering return role differs in $short");
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
            requireShape(in_array($name->toString(), $allowed, true), "Unexpected dependency in $short: $name");
        }
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            requireShape($call->name instanceof Name && in_array($call->name->toString(), $pureFunctions, true), "Unexpected function in $short");
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
                requireShape($case instanceof Stmt\EnumCase && $case->expr instanceof String_, 'An arithmetic operator must be a literal case');
                $operators[$case->name->toString()] = $case->expr->value;
            }
            requireShape($operators === $enumCases[$short], "Operator meanings differ in $short");
            continue;
        }
        requireShape($class instanceof Class_ && $class->isFinal() && $class->extends === null, "Open value class $short");
        requireShape(array_map(static fn ($name) => $name->toString(), $class->implements) === (in_array($short, $roots, true) ? ['SqlSemantics\\SemanticsIR'] : []), "Interfaces differ in $short");
        $traits = $finder->findInstanceOf($class->stmts, TraitUse::class);
        requireShape(count($traits) === 1 && count($traits[0]->traits) === 1 && $traits[0]->traits[0]->toString() === $snapshot && $traits[0]->adaptations === [], "Snapshot paths differ in $short");
        $role = str_replace('\\', '/', $short);
        $arrays = $storage[$role] ?? [];
        checkValueStorage($class, $expected, $arrays, $short, $prefix, $platform . '\\Model\\Diagnostic\\Check', $parser, $printer, $finder);
        if ($arrays !== []) {
            $listFields[$role] = array_keys($arrays);
        }
    }
    requireShape(array_diff([...array_keys($properties), ...$internal], $observed) === [] && count($observed) === count($properties) + count($internal), 'Closed IR inventory differs');
    sort($observed);
    ksort($listFields);
    requireShape(array_keys($listFields) === array_keys($storage), 'List storage catalogue is incomplete');
    sort($checkedBoundaries);
    $expectedBoundaries = array_keys($boundaries);
    sort($expectedBoundaries);
    requireShape($checkedBoundaries === $expectedBoundaries, 'Native boundary coverage differs');
    echo json_encode(['status' => 'verified', 'types' => $observed, 'checkedLists' => $listFields, 'checkedBoundaries' => $checkedBoundaries]) . "\n";
} catch (RuntimeException $failure) {
    echo json_encode(['status' => 'shape-mismatch', 'message' => $failure->getMessage()]) . "\n";
    exit(1);
}
