"""Compare native PostgreSQL input sort keys over typed field and expression graphs."""

import json
import random
import re
import sys
from functools import cmp_to_key
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_int8_fields import (
    DATA as BASE_DATA, DECLARATIONS, HEADINGS, LEFT, OID, RIGHT, address,
    evaluate as atom, field, graph as projection_graph, observer_request,
    prepare as prepare_tables, specifications as int8_specifications, table, type_of,
)
from run_server_postgres_output_order_names import DIAGNOSTIC, SEMANTIC, select
from server_cases import evaluate as arithmetic
from server_join_cases import compare, is_join, join, leaves, rows, truth
from sqlite_domain import RenderFault

LABEL = "postgres-source-order"
CONTRACT = "29350c146c94bec3fbf80346f9153fb0dcf6504d8a00ea17fe20f556d91fb978"
OBSERVER = "server_observer.php"
COLLATION = 'pg_catalog."C"'
EXTRA = [(6, 11, -3, True, "Z"), (7, -11, 12, False, "a")]
DATA = {**BASE_DATA, LEFT: [*BASE_DATA[LEFT], *EXTRA]}
MODES = {"int4": "slot", "int8": "wide-slot", "boolean": "bool-slot", "text": "text-slot",
         "arithmetic": "operation", "cast": "builtin-cast", "collated": "collated-text"}
LEFT_ID = field(LEFT, 0, "id")
RIGHT_ID = field(RIGHT, 1, "id")
RIGHT_BOOL = field(RIGHT, 1, "enabled")


def specifications():
    scope = TOOL / "postgres-source-order-v1.json"
    require(digest(scope) == CONTRACT, "postgres-source-order-contract-mismatch")
    return json.loads(scope.read_text()), int8_specifications()[1]


def source(shape):
    return table(LEFT) if shape == "physical" else join("LEFT", table(LEFT), table(RIGHT), compare("=", LEFT_ID, RIGHT_ID))


def input_sql(shape):
    left = f"public.{LEFT} l"
    return left if shape == "physical" else f"{left} LEFT JOIN public.{RIGHT} r ON l.id = r.id"


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def reference(shape, name):
    return field(LEFT, 0, name) if shape == "physical" else field(RIGHT, 1, name)


def key(shape, kind):
    alias = "l" if shape == "physical" else "r"
    if kind in ("int4", "int8", "boolean", "text"):
        name = {"int4": "id", "int8": "low", "boolean": "enabled", "text": "label"}[kind]
        return f"{alias}.{name}", reference(shape, name)
    if kind == "arithmetic":
        return f"(10 - {alias}.id)", ["operation", "-", ["integer", "10"], reference(shape, "id")]
    if kind == "cast":
        return f"CAST({alias}.enabled AS pg_catalog.int4)", ["builtin-cast", "int4", reference(shape, "enabled")]
    require(kind == "collated", "postgres-source-order-key-kind", kind)
    return f'({alias}.label COLLATE {COLLATION})', ["collated-text", "C", reference(shape, "label")]


def query(tag, shape, target, descending=False, first=False, **options):
    return select(tag, [[LEFT_ID, "result"]], [[target, descending, first], [1, False, False]], **options) | {"source": source(shape)}


def finite(tag, shape, kind, direction, nulls):
    text, operand = key(shape, kind)
    descending = direction == "descending"
    options = ("DESC" if descending else "ASC") + ("" if nulls == "implicit" else " NULLS " + nulls.upper())
    sql = f"SELECT l.id AS result FROM {input_sql(shape)} ORDER BY {text} {options}, 1"
    return sql, query(tag, shape, operand, descending, descending if nulls == "implicit" else nulls == "first")


def finite_ids(_, contract, _tag):
    finite = contract["finite"]
    grid = set(product(finite["source"], finite["kind"], finite["direction"], finite["nulls"]))
    require(len(grid) == finite["identities"], "postgres-source-order-finite-size", len(grid))
    return grid


def finite_coordinates(reply, nulls):
    ((target, descending, first), tie) = reply["orderBy"]
    require(tie == [1, False, False] and first == (descending if nulls == "implicit" else nulls == "first"),
            "postgres-source-order-finite-choice", reply)
    shape = "left-join" if is_join(reply["source"]) else "physical"
    kind = {value: name for name, value in MODES.items()}[target[0]]
    return shape, kind, "descending" if descending else "ascending", nulls


def analysis_finite_id(_, reply, item, sql):
    opts = re.search(r" (ASC|DESC)(?: NULLS (FIRST|LAST))?, 1$", sql)
    require(opts is not None, "postgres-source-order-missing-options", sql)
    direction, placement = opts.groups()
    require(reply["orderBy"][0][1] == (direction == "DESC"), "postgres-source-order-observed-direction")
    check_value(item, reply)
    return finite_coordinates(reply, placement.lower() if placement else "implicit")


def direct_finite_id(_, reply, item, direct):
    check_value(item, reply)
    first = direct["orderBy"][0][2]
    return finite_coordinates(reply, "implicit" if first is None else "first" if first else "last")


def analyzed(tag):
    left, joined = input_sql("physical"), input_sql("left-join")
    yield (f"SELECT l.id AS low FROM {left} ORDER BY l.low, 1",
           select(tag, [[LEFT_ID, "low"]], [[reference("physical", "low"), False, False], [1, False, False]]))
    yield (f"SELECT l.id AS result FROM {joined} ORDER BY ((r.low)) DESC NULLS LAST, 1",
           query(tag, "left-join", reference("left-join", "low"), True))
    output_id = ["operation", "-", ["integer", "10"], LEFT_ID]
    calculation = ["operation", "*", ["operation", "+", LEFT_ID, ["integer", "2"]], ["integer", "3"]]
    yield (f"SELECT 10 - l.id AS id FROM {left} ORDER BY ((id + 2) * 3) DESC, 1",
           select(tag, [[output_id, "id"]], [[calculation, True, True], [1, False, False]]))
    yield (f"SELECT DISTINCT l.low AS picked, l.id FROM {left} ORDER BY l.low DESC, l.id",
           select(tag, [[reference("physical", "low"), "picked"], [LEFT_ID, "id"]], [[1, True, True], [2, False, False]], distinct=True))
    projection = [[field(LEFT, 0, name), name] for name in HEADINGS[LEFT]]
    yield (f"SELECT DISTINCT l.* FROM {left} ORDER BY l.high, l.id",
           select(tag, projection, [[3, False, False], [1, False, False]], distinct=True))
    yield (f"SELECT l.id AS result FROM {joined} ORDER BY r.enabled DESC NULLS LAST, 1 LIMIT 3 OFFSET 2",
           query(tag, "left-join", RIGHT_BOOL, True, limit=[3, 2]))
    yield (f"SELECT l.id AS result FROM {left} ORDER BY CAST(l.low AS integer), 1",
           query(tag, "physical", ["builtin-cast", "int4", reference("physical", "low")]))
    yield (f"SELECT DISTINCT r.enabled AS flag, l.id FROM {joined} ORDER BY r.enabled DESC, l.id",
           select(tag, [[RIGHT_BOOL, "flag"], [LEFT_ID, "id"]], [[1, True, True], [2, False, False]], distinct=True) | {"source": source("left-join")})


def native_negatives():
    left = input_sql("physical")
    return (
        (f"SELECT l.id FROM {left} ORDER BY l.absent", "Column l.absent does not exist.", "42703"),
        (f"SELECT l.id AS only_output FROM {left} ORDER BY only_output + 1", "Column only_output does not exist.", "42703"),
        (f"SELECT DISTINCT l.id FROM {left} ORDER BY l.low", "A DISTINCT SELECT must order by projected positions.", "42P10"),
    )


def prepare(engine, db):
    initialization = prepare_tables(engine, db)
    rows_to_append = ", ".join(f"({i}, {lo}, {hi}, {'TRUE' if flag else 'FALSE'}, '{text}')" for i, lo, hi, flag, text in EXTRA)
    fixture = db.request({"fixture": [
        *(f'ALTER TABLE public.{t} ALTER COLUMN label TYPE pg_catalog.text COLLATE {COLLATION}' for t in HEADINGS),
        f"INSERT INTO public.{LEFT} VALUES {rows_to_append}",
    ]})
    catalog = db.request({"sql": "SELECT attcollation::integer AS collation FROM pg_catalog.pg_attribute WHERE "
                                 f"attrelid IN ('public.{LEFT}'::regclass, 'public.{RIGHT}'::regclass) AND attname = 'label'"})
    native = [db.request(observer_request({}, sql)) for sql, _, _ in native_negatives()]
    return {"base": initialization, "fixture": fixture, "collation": catalog, "invalid": native}


def check_preparation(_, environment, prepared):
    require(environment["database"][-1] == "pg_catalog, public", "postgres-source-order-profile")
    for name in ("base", "fixture"):
        same({"status": "fixture"}, prepared[name])
    same({"status": "rows", "fields": [["collation", "int4"]], "rows": [[950], [950]]}, prepared["collation"])
    same([{"status": "db-error", "state": state} for _, _, state in native_negatives()], prepared["invalid"])


def negatives(tag):
    for index, (sql, message, _) in enumerate(native_negatives()):
        yield request(tag, sql), {"exception": SEMANTIC if index < 2 else DIAGNOSTIC, "message": message}
    distinct_message = "A DISTINCT SELECT must order by projected positions."
    yield query(tag, "physical", reference("physical", "low"), distinct=True), {"exception": DIAGNOSTIC, "message": distinct_message}
    for operand in (["slot", 0, 1], ["wide-slot", 0, 0], ["operation", "+", ["slot", 87], ["integer", "1"]]):
        message = "An int8 slot must belong to the SELECT source with its native kind." if operand[0] == "wide-slot" else "Column slot must belong to the SELECT source."
        yield query(tag, "physical", operand), {"exception": DIAGNOSTIC, "message": message}


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        shape = rng.choice(("physical", "left-join"))
        kinds = rng.sample(list(MODES), rng.randint(2, 5))
        targets = []
        for kind in kinds:
            _, operand = key(shape, kind)
            if kind == "arithmetic":
                for _ in range(rng.randint(1, 4)):
                    operand = ["operation", rng.choice(("+", "-", "*", "|", "&", "#")), operand, ["integer", str(rng.randrange(-4, 8))]]
            targets.append([operand, rng.choice((True, False)), rng.choice((True, False))])
        bound = rng.choice((None, [rng.randrange(1, 7), rng.randrange(0, 4)]))
        yield select(tag, [[LEFT_ID, "result"]], [*targets, [1, False, False]], limit=bound) | {"source": source(shape)}


def requests(_, tag, contract):
    f = contract["finite"]
    for index, coords in enumerate(product(f["source"], f["kind"], f["direction"], f["nulls"])):
        sql, item = finite(tag, *coords)
        yield f"finite/{index}", request(tag, sql), item, None
        ordered = item["orderBy"][0]
        direct = {**item, "orderBy": [[ordered[0], ordered[1], None if coords[3] == "implicit" else ordered[2]], item["orderBy"][1]]}
        yield f"direct/{index}", direct, item, None
    examples = list(analyzed(tag))
    require(len(examples) == len(contract["analysis"]), "postgres-source-order-missing-analysis")
    for index, (sql, item) in enumerate(examples):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (input_, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, None, boundary


def symbol(target, inputs):
    if type(target) is int:
        return target
    role = target[0]
    if role == "operation":
        return role, target[1], symbol(target[2], inputs), symbol(target[3], inputs)
    if role in ("builtin-cast", "collated-text"):
        return role, target[1], symbol(target[2], inputs)
    if role == "integer":
        return tuple(target)
    return address(target, inputs)


def graph(item):
    inputs = leaves(item["source"])
    core = projection_graph({**item, "orderBy": [], "columns": []})
    projection = tuple((symbol(operand, inputs), name) for operand, name in item["columns"])
    orders = tuple((symbol(target, inputs), desc, first) for target, desc, first in item["orderBy"])
    return core, projection, orders


def evaluate(term, row):
    if term[0] == "operation":
        values = [evaluate(child, row) for child in term[2:]]
        if None in values:
            return None
        return arithmetic(["operation", term[1], *[["integer", str(v)] for v in values]], (), "postgres")
    if term[0] == "collated-text":
        require(term[1] == "C", "postgres-source-order-unknown-collation", term)
        return evaluate(term[2], row)
    return atom(term, row)


def ordered(item):
    inputs = rows(item["source"], "postgres", table_rows=lambda source: (HEADINGS[source["table"]], DATA[source["table"]]))
    result = []
    for row in inputs:
        if not truth(item["where"], row, "postgres"):
            continue
        projected = tuple(evaluate(term, row) for term, _ in item["columns"])
        keys = tuple(projected[target - 1] if type(target) is int else evaluate(target, row) for target, _, _ in item["orderBy"])
        result.append((projected, keys))
    if item["distinct"]:
        result = list(dict.fromkeys(result))

    def sort(left, right):
        for lhs, rhs, (_, descending, first) in zip(left[1], right[1], item["orderBy"]):
            if lhs == rhs:
                continue
            if lhs is None or rhs is None:
                return -1 if (lhs is None) == first else 1
            if isinstance(lhs, str):
                lhs, rhs = lhs.encode(), rhs.encode()
            return -1 if (lhs < rhs) != descending else 1
        require(left[0] == right[0], "postgres-source-order-ambiguous-supporting-fixture", [left, right])
        return 0

    result.sort(key=cmp_to_key(sort))
    if item["limit"] is not None:
        count, offset = item["limit"]
        result = result[offset:None if count is None else offset + count]
    return [list(values) for values, _ in result]


def expected(item):
    try:
        result = ordered(item)
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    fields = []
    for term, name in item["columns"]:
        native = "int4" if term[0] == "operation" else type_of(term)
        fields.append([name, native, OID[native]])
    return {"status": "rows", "fields": fields, "rows": result}


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-source-order-positive-rejected", reply)
    require(graph(reply) == graph(item), "postgres-source-order-graph",
            {"sql": reply["sql"], "expected": item, "observed": reply})


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    wanted = expected(item)
    try:
        same(wanted, actual)
    except Failure as error:
        raise Failure(error.signal, {"sql": sql, "expected": wanted, "observed": actual}) from error


def required_forms(*_):
    return {*MODES.values(), "physical", "left-join", "bound/True", "bound/False", "nested", "ascending", "descending",
            "null-first", "null-last", "multiple"}


def sample_forms(forms, reply, *_):
    forms["left-join" if is_join(reply["source"]) else "physical"] += 1
    forms[f"bound/{reply['limit'] is not None}"] += 1
    if len(reply["orderBy"]) > 2:
        forms["multiple"] += 1
    for target, descending, first in reply["orderBy"]:
        if type(target) is int:
            continue
        forms[target[0]] += 1
        forms["descending" if descending else "ascending"] += 1
        forms["null-first" if first else "null-last"] += 1
        if target[0] == "operation" and target[2][0] == "operation":
            forms["nested"] += 1


def controls():
    tag = "pg-17.2"
    item = finite(tag, "physical", "collated", "ascending", "last")[1]
    checked = expected(item)
    check_observation(item, checked, "control")
    receipts = {}
    for name, result in [("omit row", {**checked, "rows": checked["rows"][1:]}),
                         ("reverse row order", {**checked, "rows": checked["rows"][::-1]}),
                         ("changed metadata", {**checked, "fields": [["result", "int8", 20]]})]:
        try:
            check_observation(item, result, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-source-order-control-signal", name)
            receipts[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-source-order-control-undetected", name)
    return receipts


def check_boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-source-order-unexpected-fault-reply", reply)
    raise Failure("postgres-source-order-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        check_boundary(item, reply)
        return
    probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            check_boundary(rule.item, record["healthyResponse"])
            check_boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            require(witness["observed"]["status"] == "rows" or witness["observed"] == {"status": "db-error", "state": "42702"},
                    "postgres-source-order-fault-native", witness)
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-source-order-undetected-mutant", rule.name)


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    printer = ir + "Ordering/ResultSql.php"
    anchor = "$sqlPrinter->expression($key->target, $select->source)"
    joined = finite(tag, "left-join", "int8", "ascending", "last")[1]
    arithmetic_item = finite(tag, "physical", "arithmetic", "ascending", "last")[1]
    syntax, alias_item = list(analyzed(tag))[2]
    invalid, boundary = list(negatives(tag))[5]
    return [
        RenderFault("unqualify-source-sort", printer, anchor, "$sqlPrinter->expression($key->target, $select->source, 0, false)",
                    joined, "meaning-mismatch"),
        RenderFault("sort-by-wrong-source-occurrence", printer, anchor,
                    "str_replace('\"__ztd_source_1\"', '\"__ztd_source\"', " + anchor + ")", joined, "meaning-mismatch"),
        RenderFault("drop-sort-arithmetic-right", ir + "SelectSql.php",
                    " . ' OPERATOR(pg_catalog.' . $expression->operator->value . ') ' . $this->expression($expression->right, $source, $offset, $qualify)",
                    "", arithmetic_item, "meaning-mismatch"),
        RenderFault("invert-source-null-placement", printer, "$key->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST'",
                    "$key->nullsFirst ? ' NULLS LAST' : ' NULLS FIRST'", joined, "meaning-mismatch"),
        RenderFault("skip-owned-source-key-validation", ir + "Select.php", "$expressions[] = $key->target;",
                    "", {"boundary": boundary}, "postgres-source-order-invalid-published", invalid),
        RenderFault("use-result-alias-inside-sort-expression", ir + "Ordering/ResultLowering.php",
                    "        return $expression;", "        return $expression instanceof Arithmetic ? 1 : $expression;",
                    alias_item, "postgres-source-order-graph", request(tag, syntax)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
