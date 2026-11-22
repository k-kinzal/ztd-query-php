<?php

declare(strict_types=1);

use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\CountComparison;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DefaultRow;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
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
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
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
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand;
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

function operand(array $input): Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|CollatedExpression|ColumnReference|Comparison|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression
{
    return match ($input[0]) {
        'case' => new CaseExpression(
            $input[1] === null ? null : operand($input[1]),
            array_map(static fn (array $branch): CaseBranch => new CaseBranch(operand($branch[0]), operand($branch[1])), $input[2]),
            $input[3] === null ? null : operand($input[3]),
        ),
        'collate' => new CollatedExpression(operand($input[2]), Collation::from($input[1])),
        'cast' => new CastExpression(CastTarget::from($input[1]), operand($input[2])),
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
        'blob' => new BlobLiteral($input[1]),
        'real' => new RealLiteral($input[1], $input[2], $input[3], $input[4]),
        'hex' => new HexIntegerLiteral($input[1], $input[2]),
        'null' => new NullLiteral(),
        'parameter' => new Parameter($input[1]),
        'column' => new ColumnReference($input[1], $input[2] ?? 0),
        'comparison' => new Comparison(ComparisonOperator::from($input[1]), operand($input[2]), operand($input[3])),
        'logical' => new Logical(LogicalOperator::from($input[1]), operand($input[2]), operand($input[3])),
        'arithmetic' => new Arithmetic(ArithmeticOperator::from($input[1]), operand($input[2]), operand($input[3])),
    };
}

function observeOperand(Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|CollatedExpression|ColumnReference|Comparison|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression $value): array
{
    return match (true) {
        $value instanceof CaseExpression => [
            'case',
            $value->base === null ? null : observeOperand($value->base),
            array_map(static fn (CaseBranch $branch): array => [observeOperand($branch->when), observeOperand($branch->then)], $value->branches),
            $value->otherwise === null ? null : observeOperand($value->otherwise),
        ],
        $value instanceof CollatedExpression => ['collate', $value->collation->value, observeOperand($value->operand)],
        $value instanceof CastExpression => ['cast', $value->target->value, observeOperand($value->operand)],
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
        $value instanceof RealLiteral => ['real', $value->whole, $value->fraction, $value->exponent, $value->negative],
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
    ));
}

function observeSource(TableSource $source): array
{
    return [$source->schema->value, $source->table->value, array_map(
        static fn (Identifier $name): string => $name->value,
        $source->columns,
    )];
}

function direct(array $input): Select
{
    $from = $input['source'] === null ? null : source($input['source']);
    if (isset($input['local'])) {
        $from = new LocalSource(directDefinition($input['local']), array_map(
            static fn (string $name): Identifier => new Identifier($name),
            $input['local']['names'],
        ));
    }
    $columns = array_map(
        static fn (array $field): ResultColumn => new ResultColumn(operand($field[0]), new Identifier($field[1])),
        $input['columns'],
    );

    $joins = array_map(static fn (array $step): Join => new Join(
        JoinKind::from($step[0]),
        source($step[1]),
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

function analyzed(array $input): Select|GroupedSelect|Compound|Delete|Insert|Update|ValuesQuery|DropTable|CreateTable|Transaction
{
    $semantics = new Semantics();
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?: null));
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

function directCreateTable(array $input): CreateTable
{
    return new CreateTable(
        new Identifier($input['schema']),
        new Identifier($input['table']),
        array_map(static fn (array $slot): TableColumn => new TableColumn(
            new Identifier($slot[0]),
            DeclaredDatatypeName::from($slot[1]),
            $slot[2],
            isset($slot[3]) ? operand($slot[3]) : null,
        ), $input['columns']),
        $input['ifNotExists'],
    );
}

function observeCreateTable(CreateTable $value): array
{
    return [
        'status' => 'constructed',
        'operation' => 'create-table',
        'profile' => $value->profileId(),
        'schema' => $value->schema->value,
        'table' => $value->table->value,
        'columns' => array_map(
            static fn (TableColumn $column): array => [
                $column->name->value, $column->type->value, $column->notNull,
                ...($column->default === null ? [] : [observeOperand($column->default)]),
            ],
            $value->columns,
        ),
        'ifNotExists' => $value->ifNotExists,
        'sql' => $value->toString(),
    ];
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
            ($input['operation'] ?? null) === 'drop-table' => new DropTable(new Identifier($input['schema']), new Identifier($input['table']), $input['ifExists']),
            ($input['operation'] ?? null) === 'create-table' => directCreateTable($input),
            ($input['operation'] ?? null) === 'transaction' => directTransaction($input),
            ($input['operation'] ?? null) === 'values' => directValues($input),
            default => direct($input),
        };
    } catch (InvalidConstruction $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    } catch (SqlSemantics\AnalysisException $error) {
        return ['status' => 'analysis-rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
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

function observeSelect(Select $value): array
{
    $source = $value->source;
    $local = $source instanceof LocalSource ? observeLocal($source) : null;

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'source' => $source instanceof TableSource ? observeSource($source) : null,
        ...($local === null ? [] : ['local' => $local]),
        'joins' => array_map(static fn (Join $join): array => [
            $join->kind->value, observeSource($join->source), $join->on === null ? null : observeOperand($join->on),
        ], $value->joins),
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
