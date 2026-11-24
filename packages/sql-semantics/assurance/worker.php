<?php

declare(strict_types=1);

use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\CountComparison;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTypeName;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DefaultRow;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\MissingMetadataTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflictHandler;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddGeneratedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropMember;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropView;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage as GeneratedStorage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Semantics;

$root = $argv[1];
$overlay = $argv[2] ?? '';
spl_autoload_register(static function (string $class) use ($root, $overlay): void {
    foreach (['SqlSemantics\\Platform\\Sqlite\\' => 'sql-semantics-sqlite', 'SqlSemantics\\' => 'sql-semantics', 'SqlParser\\' => 'sql-parser'] as $prefix => $package) {
        if (str_starts_with($class, $prefix)) {
            $relative = $package . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $patched = $overlay . '/' . $relative;
            require $overlay !== '' && is_file($patched) ? $patched : $root . '/packages/' . $relative;
            return;
        }
    }
});

function operand(array $input): Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression
{
    return match ($input[0]) {
        'case' => new CaseExpression(
            $input[1] === null ? null : operand($input[1]),
            array_map(static fn (array $branch): CaseBranch => new CaseBranch(operand($branch[0]), operand($branch[1])), $input[2]),
            $input[3] === null ? null : operand($input[3]),
        ),
        'coalesce', 'if-null' => new Coalesce(array_map(
            static fn (array $value) => $value === ['wrong-argument'] ? new IntegerLiteral('3') : new CoalesceArgument(operand($value)),
            $input[1],
        ), $input[0] === 'if-null'),
        'concat' => new Concatenation(operand($input[1]), operand($input[2])),
        'pattern' => new PatternTest(
            PatternKind::from($input[1]),
            operand($input[3]),
            operand($input[4]),
            $input[2],
            $input[5] === null ? null : operand($input[5])
        ),
        'collate' => new CollatedExpression(operand($input[2]), Collation::from($input[1])),
        'cast' => new CastExpression(
            CastTarget::from($input[1]),
            operand($input[2]),
            !isset($input[3]) ? null : new CastTypeName(is_array($input[3]) ? hex2bin($input[3][0]) : $input[3]),
        ),
        'unary' => new UnaryExpression(UnaryKind::from($input[1]), operand($input[2])),
        'folded-membership' => new FoldedMembership($input[1], array_map(static fn (string $label): Parameter => new Parameter($label), $input[2])),
        'membership' => new MembershipTest(
            operand($input[2]),
            array_map(static fn (array $item) => $item === ['nonmember'] ? new IntegerLiteral('3') : new MembershipItem(operand($item)), $input[3]),
            $input[1],
        ),
        'between' => new BetweenTest(operand($input[2]), operand($input[3]), operand($input[4]), $input[1]),
        'truth' => new TruthTest(operand($input[3]), $input[1], $input[2]),
        'integer' => new IntegerLiteral($input[1]),
        'text' => new TextLiteral($input[1]),
        'text-bytes' => new TextLiteral(hex2bin($input[1])),
        'blob' => new BlobLiteral($input[1]),
        'real' => new RealLiteral($input[1], $input[2], $input[3], $input[4], $input[5] ?? false),
        'hex' => new HexIntegerLiteral($input[1], $input[2]),
        'null' => new NullLiteral(),
        'parameter' => new Parameter($input[1]),
        'column' => new ColumnReference($input[1], $input[2] ?? 0),
        'comparison' => new Comparison(ComparisonOperator::from($input[1]), operand($input[2]), operand($input[3])),
        'logical' => new Logical(LogicalOperator::from($input[1]), operand($input[2]), operand($input[3])),
        'arithmetic' => new Arithmetic(ArithmeticOperator::from($input[1]), operand($input[2]), operand($input[3])),
    };
}

function observeOperand(Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $value): array
{
    return match (true) {
        $value instanceof CaseExpression => [
            'case',
            $value->base === null ? null : observeOperand($value->base),
            array_map(static fn (CaseBranch $branch): array => [observeOperand($branch->when), observeOperand($branch->then)], $value->branches),
            $value->otherwise === null ? null : observeOperand($value->otherwise),
        ],
        $value instanceof Coalesce => [$value->ifNull ? 'if-null' : 'coalesce', array_map(
            static fn (CoalesceArgument $argument): array => observeOperand($argument->expression),
            $value->arguments,
        )],
        $value instanceof Concatenation => ['concat', observeOperand($value->left), observeOperand($value->right)],
        $value instanceof PatternTest => ['pattern', $value->kind->value, $value->negated, observeOperand($value->subject),
            observeOperand($value->pattern), $value->escape === null ? null : observeOperand($value->escape)],
        $value instanceof CollatedExpression => ['collate', $value->collation->value, observeOperand($value->operand)],
        $value instanceof CastExpression => ['cast', $value->target->value, observeOperand($value->operand),
            ...($value->typeName === null ? [] : [$value->typeName->value])],
        $value instanceof UnaryExpression => ['unary', $value->kind->value, observeOperand($value->operand)],
        $value instanceof FoldedMembership => ['folded-membership', $value->matches,
            array_map(static fn (Parameter $binding): string => $binding->label, $value->bindings)],
        $value instanceof MembershipTest => ['membership', $value->negated, observeOperand($value->operand),
            array_map(static fn (MembershipItem $item): array => observeOperand($item->expression), $value->items)],
        $value instanceof BetweenTest => ['between', $value->negated, observeOperand($value->operand), observeOperand($value->lower), observeOperand($value->upper)],
        $value instanceof TruthTest => ['truth', $value->desired, $value->negated, observeOperand($value->operand)],
        $value instanceof IntegerLiteral => ['integer', $value->decimal],
        $value instanceof TextLiteral => ['text', $value->value],
        $value instanceof BlobLiteral => ['blob', $value->hex],
        $value instanceof RealLiteral => ['real', $value->whole, $value->fraction, $value->exponent, $value->negative,
            ...($value->uppercaseExponent ? [true] : [])],
        $value instanceof HexIntegerLiteral => ['hex', $value->digits, $value->negated],
        $value instanceof NullLiteral => ['null'],
        $value instanceof Parameter => ['parameter', $value->label],
        $value instanceof ColumnReference => $value->source === 0 ? ['column', $value->slot] : ['column', $value->slot, $value->source],
        $value instanceof Comparison => ['comparison', $value->operator->value, observeOperand($value->left), observeOperand($value->right)],
        $value instanceof Logical => ['logical', $value->operator->value, observeOperand($value->left), observeOperand($value->right)],
        $value instanceof Arithmetic => ['arithmetic', $value->operator->value, observeOperand($value->left), observeOperand($value->right)],
    };
}

function source(array $specification): TableSource
{
    return new TableSource(new Identifier($specification[0]), new Identifier($specification[1]), array_map(
        static fn (string $name): Identifier => new Identifier($name),
        $specification[2],
    ), ExternalRelationKind::from($specification[3] ?? 'table'));
}

function observeSource(TableSource $source): array
{
    return [$source->schema->value, $source->table->value, array_map(
        static fn (Identifier $name): string => $name->value,
        $source->columns,
    ), ...($source->kind === ExternalRelationKind::View ? [$source->kind->value] : [])];
}

function directLocal(array $input): LocalSource
{
    return new LocalSource(directDefinition($input), array_map(
        static fn (string $name): Identifier => new Identifier($name),
        $input['names'],
    ));
}

function directInput(array $input, array $definitions): TableSource|LocalSource
{
    return $input[0] === 'local' ? $definitions[$input[1]] : source($input);
}

function direct(array $input): Select
{
    $definitions = array_map(directLocal(...), $input['locals'] ?? []);
    $from = $input['source'] === null ? null : directInput($input['source'], $definitions);
    if (isset($input['local'])) {
        $from = directLocal($input['local']);
    }
    $columns = array_map(
        static fn (array $field): ResultColumn => new ResultColumn(operand($field[0]), new Identifier($field[1])),
        $input['columns'],
    );

    $joins = array_map(static fn (array $step): Join => new Join(
        JoinKind::from($step[0]),
        directInput($step[1], $definitions),
        $step[2] === null ? null : operand($step[2]),
    ), $input['joins'] ?? []);
    $orderBy = array_map(static fn (array $term): Order => new Order(...$term), $input['orderBy'] ?? []);
    return new Select($columns, $from, $joins, isset($input['where']) ? operand($input['where']) : null, $orderBy, $input['distinct'] ?? false, directLimit($input));
}

function directDefinition(array $input): ValuesQuery|RecursiveDefinition
{
    $anchor = directValues($input);
    if (!isset($input['step'])) {
        return $anchor;
    }

    return new RecursiveDefinition($anchor, array_map(
        static fn (array $expression): RecursiveColumn|Identifier => $expression[0] === 'identifier'
            ? new Identifier($expression[1])
            : new RecursiveColumn(operand($expression)),
        $input['step'],
    ), isset($input['where']) ? operand($input['where']) : null, $input['unionAll']);
}

function directLimit(array $input): ?Limit
{
    if (!isset($input['limit'])) {
        return null;
    }
    [$count, $offset, $offsetFirst] = $input['limit'];

    return new Limit(operand($count), $offset === null ? null : operand($offset), $offsetFirst);
}

function directCompound(array $input): Compound
{
    $first = directArm($input['first']);
    $steps = array_map(
        static fn (array $step): CompoundStep => new CompoundStep(CompoundOperator::from($step[0]), directArm($step[1])),
        $input['steps'],
    );
    $orders = array_map(static fn (array $term): Order => new Order(...$term), $input['orderBy']);

    return new Compound($first, $steps, $orders, directLimit($input));
}

function directArm(array $input): Select|ValuesQuery
{
    return match ($input['operation']) {
        'select' => direct($input),
        'values' => directValues($input),
    };
}

function observeArm(Select|ValuesQuery $query): array
{
    if ($query instanceof ValuesQuery) {
        return ['operation' => 'values', 'rows' => valueRows($query)];
    }
    $observed = observeSelect($query);
    unset($observed['status'], $observed['profile'], $observed['sql']);

    return ['operation' => 'select', ...$observed];
}

function observeCompound(Compound $query): array
{
    return [
        'status' => 'constructed',
        'operation' => 'compound',
        'profile' => $query->profileId(),
        'first' => observeArm($query->first),
        'steps' => array_map(
            static fn (CompoundStep $step): array => [$step->operator->value, observeArm($step->query)],
            $query->steps,
        ),
        'orderBy' => array_map(static fn (Order $term): array => [$term->position, $term->descending, $term->nullsFirst], $query->orderBy),
        'limit' => observeLimit($query->limit),
        'sql' => $query->toString(),
    ];
}

function analyzed(array $input): Select|GroupedSelect|Compound|Delete|Insert|Update|ValuesQuery|DropTable|DropView|CreateTable|CreateTableAs|CreateView|RenameTable|RenameColumn|AddColumn|AddGeneratedColumn|DropColumn|CreateIndex|DropIndex|Reindex|Analyze|FlagPragma|TableInspection|TableListing|Transaction
{
    $path = isset($input['searchPath']) ? new SearchPath(...$input['searchPath']) : null;
    $semantics = new Semantics(searchPath: $path);
    $catalogAnalyzer = ($input['foreignProfile'] ?? false)
        ? new Semantics(parameters: ParameterStyle::Named, searchPath: $path)
        : $semantics;
    $context = isset($input['catalogComplete'])
        ? $catalogAnalyzer->context($input['declarations'], $input['catalogComplete'])
        : $catalogAnalyzer->context($input['declarations'] ?: null);

    return $semantics->analyze($input['sql'], $context);
}

function directDelete(array $input): Delete
{
    return new Delete(
        source($input['target']),
        isset($input['where']) ? operand($input['where']) : null,
        directReturning($input['returning'] ?? []),
    );
}

function observeDelete(Delete $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'delete',
        'profile' => $value->profileId(),
        'target' => observeSource($value->target),
        'where' => $value->where === null ? null : observeOperand($value->where),
        'returning' => observeReturning($value->returning),
        'sql' => $value->toString(),
    ];
}

function directReturning(array $fields): array
{
    return array_map(static fn (array $field): ReturnedColumn => new ReturnedColumn(
        new ColumnReference($field[0][1], $field[0][2] ?? 0),
        new Identifier($field[1]),
    ), $fields);
}

function observeReturning(array $fields): array
{
    return array_map(
        static fn (ReturnedColumn $column): array => [observeOperand($column->reference), $column->name->value],
        $fields,
    );
}

function directInsert(array $input): Insert
{
    $rows = match (true) {
        ($input['defaults'] ?? false) === true => new DefaultRow(),
        isset($input['query']) => direct($input['query']),
        default => directValues($input),
    };

    $target = source($input['target']);
    $writes = $input['writes'] ?? array_map(static fn (int $slot): array => ['column', $slot], array_keys($target->columns));

    return new Insert(
        $target,
        array_map(operand(...), $writes),
        $rows,
        InsertConflict::from($input['conflict']),
        directReturning($input['returning'] ?? []),
        InsertConflictHandler::from($input['handler'] ?? 'none'),
    );
}

function observeInsert(Insert $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'insert',
        'profile' => $value->profileId(),
        'target' => observeSource($value->target),
        'writes' => array_map(observeOperand(...), $value->writes),
        'returning' => observeReturning($value->returning),
        'conflict' => $value->conflict->value,
        'handler' => $value->handler->value,
        ...match (true) {
            $value->input instanceof DefaultRow => ['defaults' => true],
            $value->input instanceof Select => ['query' => observeArm($value->input)],
            $value->input instanceof ValuesQuery => ['rows' => valueRows($value->input)],
        },
        'sql' => $value->toString(),
    ];
}

function directUpdate(array $input): Update
{
    return new Update(
        source($input['target']),
        array_map(static fn (array $pair): UpdateAssignment => new UpdateAssignment($pair[0], operand($pair[1])), $input['assignments']),
        isset($input['where']) ? operand($input['where']) : null,
        UpdateConflict::from($input['conflict']),
        directReturning($input['returning'] ?? []),
    );
}

function observeUpdate(Update $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'update',
        'profile' => $value->profileId(),
        'target' => observeSource($value->target),
        'conflict' => $value->conflict->value,
        'assignments' => array_map(
            static fn (UpdateAssignment $assignment): array => [$assignment->targetSlot, observeOperand($assignment->value)],
            $value->assignments,
        ),
        'where' => $value->where === null ? null : observeOperand($value->where),
        'returning' => observeReturning($value->returning),
        'sql' => $value->toString(),
    ];
}

function directValues(array $input): ValuesQuery
{
    return new ValuesQuery(array_map(static fn (array $row): ValueRow => new ValueRow(array_map(operand(...), $row)), $input['rows']));
}

function observeValues(ValuesQuery $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'values',
        'profile' => $value->profileId(),
        'rows' => valueRows($value),
        'sql' => $value->toString(),
    ];
}

function valueRows(ValuesQuery $value): array
{
    return array_map(static fn (ValueRow $row): array => array_map(observeOperand(...), $row->values), $value->rows);
}

function directTableKey(array $value): PrimaryKey|UniqueKey
{
    $terms = array_map(static fn (array $term): KeyTerm => new KeyTerm($term[0], $term[1], Collation::from($term[2])), $value['terms']);
    $conflict = isset($value['conflict']) ? KeyConflict::from($value['conflict']) : null;

    return match ($value['kind']) {
        'primary' => new PrimaryKey($terms, $value['inline'], $conflict, $value['autoIncrement'] ?? false),
        'unique' => new UniqueKey($terms, $value['inline'], $conflict),
    };
}

function observeTableKey(PrimaryKey|UniqueKey $value): array
{
    return [
        'kind' => $value instanceof PrimaryKey ? 'primary' : 'unique',
        'terms' => array_map(static fn (KeyTerm $term): array => [$term->slot, $term->descending, $term->collation->value], $value->terms),
        'inline' => $value->inline,
        'conflict' => $value->conflict?->value,
        ...($value instanceof PrimaryKey ? ['autoIncrement' => $value->autoIncrement] : []),
    ];
}

function observePrimaryKey(?PrimaryKey $value): ?array
{
    if ($value === null) {
        return null;
    }
    $key = observeTableKey($value);
    unset($key['kind']);
    $key['conflict'] ??= 'ABORT';

    return $key;
}

function directCreateTable(array $input): CreateTable
{
    $keys = $input['keys'] ?? (isset($input['primaryKey']) ? [['kind' => 'primary', ...$input['primaryKey']]] : []);

    return new CreateTable(
        new Identifier($input['schema']),
        new Identifier($input['table']),
        array_map(directSchemaColumn(...), $input['columns']),
        $input['ifNotExists'],
        $input['strict'] ?? false,
        $input['withoutRowid'] ?? false,
        array_map(directTableKey(...), $keys),
        array_map(directSchemaCheck(...), $input['checks'] ?? []),
    );
}

function directSchemaCheck(array $check): CheckConstraint
{
    return new CheckConstraint(directCheckLabel($check['label']), operand($check['condition']), $check['column']);
}

function observeSchemaCheck(CheckConstraint $check): array
{
    return ['label' => observeCheckLabel($check->label), 'column' => $check->column, 'condition' => observeOperand($check->condition)];
}

function directCheckLabel(string|array $source): Identifier|ImplicitCheckLabel
{
    if (is_string($source)) {
        return new Identifier($source);
    }

    return new ImplicitCheckLabel(
        new Identifier($source['name']),
        ComparisonSpelling::from($source['glyph']),
        $source['quoted'],
        $source['before'],
        $source['after'],
        $source['zeroes']
    );
}

function observeCheckLabel(Identifier|ImplicitCheckLabel $label): string|array
{
    if ($label instanceof Identifier) {
        return $label->value;
    }

    return [
        'name' => $label->columnName->value, 'glyph' => $label->token->value,
        'quoted' => $label->quoted, 'before' => $label->beforeOperator, 'after' => $label->afterOperator,
        'zeroes' => $label->leadingZeroes,
    ];
}

function observeCreateTable(CreateTable $value): array
{
    $generated = [];
    foreach ($value->columns as $slot => $column) {
        if ($column->generated !== null) {
            $generated[] = [$slot, $column->generated->mode->value, observeOperand($column->generated->expression)];
        }
    }

    return [
        'status' => 'constructed',
        'operation' => 'create-table',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'columns' => array_map(
            static fn (TableColumn $column): array => [
                $column->name->value, $column->type->value,
                match ($column->notNull) {
                    null => false,
                    NotNullConflict::Abort => true,
                    default => $column->notNull->value,
                },
                ...($column->default === null ? [] : [observeSchemaDefault($column->default)]),
            ],
            $value->columns,
        ),
        'ifNotExists' => $value->ifNotExists,
        'strict' => $value->strict,
        'withoutRowid' => $value->withoutRowid,
        'primaryKey' => observePrimaryKey($value->primaryKey),
        'keys' => array_map(observeTableKey(...), $value->keys),
        'checks' => array_map(observeSchemaCheck(...), $value->checks),
        'collations' => array_map(static fn (TableColumn $column): string => $column->collation->value, $value->columns),
        ...($generated === [] ? [] : ['generated' => $generated]),
        'sql' => $value->toString(),
    ];
}

function directCreateTableAs(array $input): CreateTableAs
{
    $query = $input['query'];
    $value = ($query['operation'] ?? 'select') === 'values' ? directValues($query) : direct($query);

    return new CreateTableAs(new Identifier($input['schema']), new Identifier($input['table']), $value, $input['ifNotExists']);
}

function observeCreateTableAs(CreateTableAs $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'create-as',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'ifNotExists' => $value->ifNotExists,
        'query' => $value->query instanceof ValuesQuery ? observeValues($value->query) : observeSelect($value->query),
        'columns' => array_map(static fn (CreatedColumn $column): array => [$column->name->value, $column->type->value], $value->columns()),
        'sql' => $value->toString(),
    ];
}

function directCreateView(array $input): CreateView
{
    return new CreateView(
        new Identifier($input['schema']),
        new Identifier($input['view']),
        array_map(static fn (string $name): Identifier => new Identifier($name), $input['columns']),
        match ($input['query']['operation']) {
            'grouped' => directGrouped($input['query']),
            'compound' => directCompound($input['query']),
            'select', 'values' => directArm($input['query']),
        },
        $input['ifNotExists'],
    );
}

function observeCreateView(CreateView $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'create-view',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'view' => $value->view->value,
        'ifNotExists' => $value->ifNotExists,
        'query' => observeViewBody($value->query),
        'columns' => array_map(static fn (Identifier $name): string => $name->value, $value->columns),
        'sql' => $value->toString(),
    ];
}

function observeViewBody(Compound|GroupedSelect|Select|ValuesQuery $query): array
{
    $body = match (true) {
        $query instanceof GroupedSelect => observeGrouped($query),
        $query instanceof Compound => observeCompound($query),
        $query instanceof Select, $query instanceof ValuesQuery => observeArm($query),
    };
    unset($body['status'], $body['profile'], $body['sql']);

    return $body;
}

function directTransaction(array $input): Transaction
{
    $name = isset($input['nameHex']) ? hex2bin($input['nameHex']) : ($input['name'] ?? null);

    return new Transaction(TransactionCommand::from($input['command']), $name === null ? null : new SavepointName($name));
}

function directCount(array $input): Count
{
    return new Count($input[1] === null ? null : operand($input[1]), $input[2] === null ? null : Collation::from($input[2]));
}

function observeCount(Count $value): array
{
    return ['count', $value->argument === null ? null : observeOperand($value->argument), $value->distinctCollation?->value];
}

function directGrouped(array $input): GroupedSelect
{
    $keys = [];
    foreach ($input['keys'] as [$column, $collation]) {
        $keys[] = new GroupKey(operand($column), Collation::from($collation));
    }
    $columns = [];
    foreach ($input['columns'] as [$field, $name]) {
        $value = $field[0] === 'count' ? directCount($field) : operand($field);
        $columns[] = new GroupColumn($value, new Identifier($name));
    }

    $condition = $input['having'] ?? null;
    return new GroupedSelect(
        $input['source'] === null ? null : source($input['source']),
        $keys,
        $columns,
        $input['where'] === null ? null : operand($input['where']),
        $condition === null ? null : new CountComparison(
            directCount($condition[0]),
            ComparisonOperator::from($condition[1]),
            operand($condition[2]),
            $condition[3],
        ),
    );
}

function observeGrouped(GroupedSelect $query): array
{
    $columns = [];
    foreach ($query->columns as $field) {
        $value = $field->value;
        $columns[] = [$value instanceof Count ? observeCount($value) : observeOperand($value), $field->name->value];
    }

    return [
        'status' => 'constructed', 'operation' => 'grouped', 'profile' => $query->profileId(), 'sql' => $query->toString(),
        'source' => $query->source === null ? null : observeSource($query->source),
        'keys' => array_map(static fn (GroupKey $key): array => [observeOperand($key->column), $key->collation->value], $query->keys),
        'columns' => $columns, 'where' => $query->where === null ? null : observeOperand($query->where),
        'having' => $query->having === null ? null : [
            observeCount($query->having->count), $query->having->operator->value,
            observeOperand($query->having->threshold), $query->having->thresholdFirst,
        ],
    ];
}

function directRename(array $input): RenameTable|RenameColumn
{
    $schema = new Identifier($input['schema']);
    $table = new Identifier($input['table']);
    $destination = new Identifier($input['destination']);

    return $input['column'] === null ? new RenameTable($schema, $table, $destination)
        : new RenameColumn($schema, $table, new Identifier($input['column']), $destination, $input['quoteReferences']);
}

function observeSchemaDefault(object $value): array
{
    return $value instanceof ColumnDefaultExpression ? ['computed', observeOperand($value->expression)] : observeOperand($value);
}

function directSchemaColumn(array $slot): TableColumn
{
    $default = $slot[3] ?? null;

    return new TableColumn(
        new Identifier($slot[0]),
        DeclaredDatatypeName::from($slot[1]),
        match ($slot[2]) {
            false => null,
            true => NotNullConflict::Abort,
            default => NotNullConflict::tryFrom($slot[2]) ?? $slot[2],
        },
        $default === null ? null : ($default[0] === 'computed' ? new ColumnDefaultExpression(operand($default[1])) : operand($default)),
        Collation::from($slot[4] ?? 'BINARY'),
        isset($slot[5]) ? new ColumnGeneration(GeneratedStorage::from($slot[5][0]), operand($slot[5][1])) : null,
    );
}

function directCreateIndex(array $input): CreateIndex
{
    return new CreateIndex(
        new Identifier($input['schema']),
        new Identifier($input['index']),
        new Identifier($input['table']),
        array_map(static fn ($name) => is_string($name) ? new Identifier($name) : $name, $input['heading']),
        array_map(static fn ($term) => is_array($term) ? new IndexTerm(operand($term[0]), $term[1]) : $term, $input['terms']),
        $input['where'] === null ? null : operand($input['where']),
        $input['unique'],
        $input['ifNotExists'],
    );
}

function observeCreateIndex(CreateIndex $value, bool $render): array
{
    return [
        'status' => 'constructed',
        'operation' => 'create-index',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'index' => $value->index->value,
        'table' => $value->table->value,
        'heading' => array_map(static fn (Identifier $name): string => $name->value, $value->heading),
        'terms' => array_map(static fn (IndexTerm $term): array => [observeOperand($term->expression), $term->descending], $value->terms),
        'where' => $value->where === null ? null : observeOperand($value->where),
        'unique' => $value->unique,
        'ifNotExists' => $value->ifNotExists,
        ...($render ? ['sql' => $value->toString()] : []),
    ];
}

function directFlagPragma(array $input): FlagPragma
{
    return new FlagPragma(
        FlagName::from($input['flag']),
        $input['change'] === null ? null : FlagChange::from($input['change']),
        $input['schema'] === null ? null : new Identifier($input['schema']),
    );
}

function observeFlagPragma(FlagPragma $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'flag-pragma',
        'profile' => $value->profileId(),
        'flag' => $value->name->value,
        'change' => $value->change?->value,
        'schema' => $value->schema?->value,
        'sql' => $value->toString(),
    ];
}

function directTableMetadata(array $input): TableInspection|TableListing
{
    $schema = $input['schema'] === null ? null : new Identifier($input['schema']);
    if ($input['pragma'] === 'table_list') {
        return new TableListing($schema, $input['argument'] === null ? null : new Identifier($input['argument']));
    }
    $role = $input['target'];
    $target = match ($role[0] ?? null) {
        null => null,
        'catalog' => new CatalogTable(new Identifier($role[1])),
        'missing' => new MissingMetadataTable(new Identifier($role[1])),
        'relation' => new RelationTarget(new Identifier($role[1]), new Identifier($role[2]), ExternalRelationKind::from($role[3])),
    };

    return new TableInspection(TableInspectionKind::from($input['pragma']), $target, $schema);
}

function observeTableMetadata(TableInspection|TableListing $value): array
{
    $target = $value instanceof TableInspection ? $value->target : null;
    return [
        'status' => 'constructed',
        'operation' => 'table-metadata',
        'profile' => $value->profileId(),
        'pragma' => $value instanceof TableInspection ? $value->kind->value : 'table_list',
        'schema' => $value->schema?->value,
        'filter' => $value instanceof TableListing ? $value->nameFilter?->value : null,
        'target' => match (true) {
            $target === null => null,
            $target instanceof CatalogTable => ['catalog', $target->schema->value],
            $target instanceof MissingMetadataTable => ['missing', $target->name->value],
            $target instanceof RelationTarget => ['relation', $target->schema->value, $target->name->value, $target->kind->value],
        },
        'sql' => $value->toString(),
    ];
}

function maintenanceTarget(array $role): Identifier|Collation|RelationTarget|IndexTarget|null
{
    return match ($role[0]) {
        'all' => null,
        'schema' => new Identifier($role[1]),
        'collation' => Collation::from($role[1]),
        'relation' => new RelationTarget(new Identifier($role[1]), new Identifier($role[2]), ExternalRelationKind::from($role[3])),
        'index' => new IndexTarget(new Identifier($role[1]), new Identifier($role[2]), new Identifier($role[3])),
    };
}

function observeMaintenance(Reindex|Analyze $value): array
{
    $target = $value->target;
    return [
        'status' => 'constructed',
        'operation' => $value instanceof Analyze ? 'analyze-statistics' : 'reindex',
        'profile' => $value->profileId(),
        'target' => match (true) {
            $target === null => ['all'],
            $target instanceof Identifier => ['schema', $target->value],
            $target instanceof Collation => ['collation', $target->value],
            $target instanceof RelationTarget => ['relation', $target->schema->value, $target->name->value, $target->kind->value],
            $target instanceof IndexTarget => ['index', $target->schema->value, $target->name->value, $target->table->value],
        },
        'sql' => $value->toString(),
    ];
}

function directDropIndex(array $input): DropIndex
{
    $name = new Identifier($input['name']);
    $target = match ($input['targetRole']) {
        'installed' => new IndexReference(new Identifier($input['schema']), $name, new Identifier($input['table'])),
        'absent' => new MissingIndex($name, $input['schema'] === null ? null : new Identifier($input['schema'])),
    };

    return new DropIndex($target, $input['ifExists']);
}

function observeDropIndex(DropIndex $value, bool $render): array
{
    $target = $value->target;
    return [
        'status' => 'constructed',
        'operation' => 'drop-index',
        'profile' => $value->profileId(),
        'targetRole' => $target instanceof IndexReference ? 'installed' : 'absent',
        'schema' => $target->schema?->value,
        'name' => $target->name->value,
        'table' => $target instanceof IndexReference ? $target->table->value : null,
        'ifExists' => $value->ifExists,
        ...($render ? ['sql' => $value->toString()] : []),
    ];
}

function directDropColumn(array $input): DropColumn
{
    return new DropColumn(
        new Identifier($input['schema']),
        new Identifier($input['table']),
        array_map(static fn ($member) => is_array($member) ? new DropMember(new Identifier($member[0]), $member[1], $member[2]) : $member, $input['heading']),
        $input['slot'],
    );
}

function observeDropColumn(DropColumn $value, bool $render): array
{
    return [
        'status' => 'constructed',
        'operation' => 'drop-column',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'heading' => array_map(static fn (DropMember $member): array => [$member->name->value, $member->generated, $member->protected], $value->heading),
        'slot' => $value->slot,
        ...($render ? ['sql' => $value->toString()] : []),
    ];
}

function directAddColumn(array $input): AddColumn|AddGeneratedColumn
{
    $schema = new Identifier($input['schema']);
    $table = new Identifier($input['table']);
    $heading = array_map(static fn ($part) => is_string($part) ? new Identifier($part) : $part, $input['priorHeading']);
    $column = directSchemaColumn($input['column']);
    $checks = array_map(static fn ($check) => is_array($check) ? directSchemaCheck($check) : $check, $input['checks'] ?? []);

    return $input['operation'] === 'add-generated-column'
        ? new AddGeneratedColumn($schema, $table, $heading, $input['strict'], $column, $checks)
        : new AddColumn($schema, $table, $heading, $input['strict'], $column, $checks);
}

function observeAddColumn(AddColumn|AddGeneratedColumn $value, bool $render): array
{
    $column = $value->column;

    return [
        'status' => 'constructed',
        'operation' => $value instanceof AddGeneratedColumn ? 'add-generated-column' : 'add-column',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'priorHeading' => array_map(static fn (Identifier $name): string => $name->value, $value->priorHeading),
        'strict' => $value->strict,
        'column' => [
            $column->name->value, $column->type->value, $column->notNull?->value ?? false,
            $column->default === null ? null : observeSchemaDefault($column->default), $column->collation->value,
            ...($column->generated === null ? [] : [[$column->generated->mode->value, observeOperand($column->generated->expression)]]),
        ],
        'checks' => array_map(observeSchemaCheck(...), $value->checks),
        ...($render ? ['sql' => $value->toString()] : []),
    ];
}

function observeRename(RenameTable|RenameColumn $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'rename',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'column' => $value instanceof RenameColumn ? $value->column->value : null,
        'destination' => $value->destination->value,
        'quoteReferences' => $value instanceof RenameColumn ? $value->quoteReferences : null,
        'sql' => $value->toString(),
    ];
}

function runCase(array $input): array
{
    try {
        $value = match (true) {
            isset($input['sql']) => analyzed($input),
            ($input['operation'] ?? null) === 'grouped' => directGrouped($input),
            ($input['operation'] ?? null) === 'compound' => directCompound($input),
            ($input['operation'] ?? null) === 'delete' => directDelete($input),
            ($input['operation'] ?? null) === 'insert' => directInsert($input),
            ($input['operation'] ?? null) === 'update' => directUpdate($input),
            ($input['operation'] ?? null) === 'rename' => directRename($input),
            in_array($input['operation'] ?? null, ['add-column', 'add-generated-column'], true) => directAddColumn($input),
            ($input['operation'] ?? null) === 'create-index' => directCreateIndex($input),
            ($input['operation'] ?? null) === 'table-metadata' => directTableMetadata($input),
            ($input['operation'] ?? null) === 'flag-pragma' => directFlagPragma($input),
            ($input['operation'] ?? null) === 'reindex' => new Reindex(maintenanceTarget($input['target'])),
            ($input['operation'] ?? null) === 'analyze-statistics' => new Analyze(maintenanceTarget($input['target'])),
            ($input['operation'] ?? null) === 'drop-index' => directDropIndex($input),
            ($input['operation'] ?? null) === 'drop-column' => directDropColumn($input),
            ($input['operation'] ?? null) === 'drop-table' => new DropTable(new Identifier($input['schema']), new Identifier($input['table']), $input['ifExists']),
            ($input['operation'] ?? null) === 'drop-view' => new DropView(new Identifier($input['schema']), new Identifier($input['view']), $input['ifExists']),
            ($input['operation'] ?? null) === 'create-table' => directCreateTable($input),
            ($input['operation'] ?? null) === 'create-as' => directCreateTableAs($input),
            ($input['operation'] ?? null) === 'create-view' => directCreateView($input),
            ($input['operation'] ?? null) === 'transaction' => directTransaction($input),
            ($input['operation'] ?? null) === 'values' => directValues($input),
            default => direct($input),
        };
    } catch (InvalidConstruction $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    } catch (SqlSemantics\AnalysisException $error) {
        return ['status' => 'analysis-rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    }

    if ($value instanceof CreateIndex) {
        return observeCreateIndex($value, ($input['probe'] ?? null) !== 'create-index-construction');
    }
    if ($value instanceof TableInspection || $value instanceof TableListing) {
        return observeTableMetadata($value);
    }
    if ($value instanceof FlagPragma) {
        return observeFlagPragma($value);
    }
    if ($value instanceof Reindex || $value instanceof Analyze) {
        return observeMaintenance($value);
    }
    if ($value instanceof DropIndex) {
        return observeDropIndex($value, ($input['probe'] ?? null) !== 'drop-index-construction');
    }
    if ($value instanceof DropColumn) {
        return observeDropColumn($value, ($input['probe'] ?? null) !== 'drop-column-construction');
    }
    if ($value instanceof AddColumn || $value instanceof AddGeneratedColumn) {
        return observeAddColumn($value, !in_array($input['probe'] ?? null, ['add-generated-construction', 'computed-default-construction', 'appended-check-construction'], true));
    }
    if ($value instanceof RenameTable || $value instanceof RenameColumn) {
        return observeRename($value);
    }
    if ($value instanceof GroupedSelect) {
        return observeGrouped($value);
    }
    if ($value instanceof Compound) {
        return observeCompound($value);
    }
    if ($value instanceof Delete) {
        return observeDelete($value);
    }
    if ($value instanceof Insert) {
        return observeInsert($value);
    }
    if ($value instanceof Update) {
        return observeUpdate($value);
    }
    if ($value instanceof DropView) {
        return [
            'status' => 'constructed',
            'operation' => 'drop-view',
            'profile' => $value->profileId(),
            'schema' => $value->schema->value,
            'view' => $value->view->value,
            'ifExists' => $value->ifExists,
            'sql' => $value->toString(),
        ];
    }
    if ($value instanceof DropTable) {
        return [
            'status' => 'constructed',
            'operation' => 'drop-table',
            'profile' => $value->profileId(),
            'schema' => $value->schema->value,
            'table' => $value->table->value,
            'ifExists' => $value->ifExists,
            'sql' => $value->toString(),
        ];
    }
    if ($value instanceof CreateTable) {
        return observeCreateTable($value);
    }
    if ($value instanceof CreateView) {
        return observeCreateView($value);
    }
    if ($value instanceof CreateTableAs) {
        return observeCreateTableAs($value);
    }
    if ($value instanceof Transaction) {
        return [
            'status' => 'constructed',
            'operation' => 'transaction',
            'profile' => $value->profileId(),
            'command' => $value->command->value,
            'name' => $value->name?->value,
            'sql' => $value->toString(),
        ];
    }
    if ($value instanceof ValuesQuery) {
        return observeValues($value);
    }

    if (($input['probe'] ?? '') === 'snapshot') {
        $actions = [
            static fn () => clone $value,
            static fn () => serialize($value),
            static fn () => unserialize('O:' . strlen(Select::class) . ':"' . Select::class . '":0:{}'),
            static fn () => $value->extra = true,
        ];
        $blocked = [];
        foreach ($actions as $action) {
            try {
                $action();
                $blocked[] = false;
            } catch (InvalidConstruction) {
                $blocked[] = true;
            }
        }
        return ['status' => 'snapshot', 'blocked' => $blocked];
    }

    return observeSelect($value);
}

function observeLimit(?Limit $limit): ?array
{
    return $limit === null ? null : [
        observeOperand($limit->count), $limit->offset === null ? null : observeOperand($limit->offset), $limit->offsetFirst,
    ];
}

function observeLocal(LocalSource $source): array
{
    $query = $source->query;
    $names = array_map(static fn (Identifier $name): string => $name->value, $source->columns);
    if ($query instanceof ValuesQuery) {
        return ['names' => $names, 'rows' => valueRows($query)];
    }

    return [
        'names' => $names,
        'rows' => valueRows($query->anchor),
        'step' => array_map(static fn (RecursiveColumn $column): array => observeOperand($column->expression), $query->step),
        'where' => $query->where === null ? null : observeOperand($query->where),
        'unionAll' => $query->unionAll,
    ];
}

function observeInput(TableSource|LocalSource $source, array &$ids, array &$definitions): array
{
    if ($source instanceof TableSource) {
        return observeSource($source);
    }
    $identity = spl_object_id($source);
    if (!isset($ids[$identity])) {
        $ids[$identity] = count($definitions);
        $definitions[] = observeLocal($source);
    }

    return ['local', $ids[$identity]];
}

function observeSelect(Select $value): array
{
    $source = $value->source;
    $local = $source instanceof LocalSource && $value->joins === [] ? observeLocal($source) : null;
    $ids = $definitions = $joins = [];
    $first = $source === null || $local !== null ? null : observeInput($source, $ids, $definitions);
    foreach ($value->joins as $join) {
        $joins[] = [
            $join->kind->value, observeInput($join->source, $ids, $definitions),
            $join->on === null ? null : observeOperand($join->on),
        ];
    }

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'source' => $first,
        ...($local === null ? [] : ['local' => $local]),
        ...($definitions === [] ? [] : ['locals' => $definitions]),
        'joins' => $joins,
        'where' => $value->where === null ? null : observeOperand($value->where),
        'orderBy' => array_map(static fn (Order $order): array => [$order->position, $order->descending, $order->nullsFirst], $value->orderBy),
        'distinct' => $value->distinct,
        'limit' => observeLimit($value->limit),
        'columns' => array_map(static fn (ResultColumn $field): array => [observeOperand($field->expression), $field->name->value], $value->columns),
        'sql' => $value->toString(),
    ];
}

echo json_encode(['php' => PHP_VERSION, 'intSize' => PHP_INT_SIZE], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    try {
        $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $observed = array_is_list($input) ? array_map(runCase(...), $input) : runCase($input);
        echo json_encode($observed, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['status' => 'error', 'exception' => $error::class, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
}
