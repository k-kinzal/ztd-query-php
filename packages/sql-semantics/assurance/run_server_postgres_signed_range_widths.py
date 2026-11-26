"""Check typed PostgreSQL distinctness and range predicates over both signed widths."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import OBSERVER, main
from run_server_postgres_int8_fields import (
    LEFT, RIGHT, MAX, MIN, OID, check_preparation as check_rows, field, prepare as prepare_rows,
    specifications as signed_fields, table, type_of,
)
from run_server_postgres_signed_comparison_widths import DIAGNOSTIC, evaluate, graph, input_rows, native_request, query
from server_delete_cases import literal
from server_join_cases import compare, is_join, join
from server_result_cases import match, result_window
from sqlite_domain import RenderFault

LABEL = "postgres-signed-range-widths"
CONTRACT_NAME = "postgres-signed-range-widths-v1.json"
CONTRACT = "b38da212215669bd505e0a84aba3c7cada6f4c918c34f6d2a0cd7e4e9d5cf986"
COMPLETE_SEEDS = True
L, R = f"public.{LEFT}", f"public.{RIGHT}"
DISTINCT = "int4-distinctness"
RANGE = "int4-range"
MODES = {"distinct": (False,), "not-distinct": (True,), "asymmetric": (False, False),
         "not-asymmetric": (True, False), "symmetric": (False, True), "not-symmetric": (True, True)}
RENDER_MODE = {DISTINCT: {flags: name for name, flags in MODES.items() if len(flags) == 1},
               RANGE: {flags: name for name, flags in MODES.items() if len(flags) == 2}}
LEAVES = {"slot": "int4-field", "wide-slot": "int8-field", "wide-null": "int8-null"}
I, W = field(LEFT, 0, "id"), field(LEFT, 0, "low")
J, X = field(RIGHT, 1, "id"), field(RIGHT, 1, "high")
N = ["wide-null"]
NULL = ["null"]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-signed-range-contract-mismatch")
    return json.loads(path.read_text()), signed_fields()[1]


def predicate(mode, *leaves):
    flags = MODES[mode]
    return [DISTINCT if len(flags) == 1 else RANGE, *flags, *leaves]


def wide(number):
    return ["wide-integer", str(number)]


def source(placement, term):
    return join("LEFT", table(LEFT), table(RIGHT), term) if placement == "on" else join("CROSS", table(LEFT), table(RIGHT))


def scenario(tag, placement, term, *, fields=None, order=()):
    if fields is None:
        fields = [[I, "left_id"], [J, "right_id"]]
    if placement == "projection":
        fields = [[term, "answer"], *fields]
    return query(tag, source(placement, term), fields, term if placement == "where" else None, order=order)


def leaf(role, position):
    return {
        "int4-field": J if position == 1 else I,
        "int8-field": X if position == 1 else W,
        "int8-null": N,
    }[role]


def finite(tag, placement, mode, roles):
    return scenario(tag, placement, predicate(mode, *(leaf(role, position) for position, role in enumerate(roles))))


def finite_ids(_, contract, _tag):
    f = contract["finite"]
    identities = {(placement, form, *types)
                  for placement in f["placements"]
                  for arity, family in ((2, "distinctness"), (3, "range"))
                  for form in f[family] for types in product(f["roles"], repeat=arity)}
    require(len(identities) == f["identities"], "postgres-signed-range-finite-size")
    return identities


def finite_id(_, reply, _expected):
    relation = reply["source"]
    if relation["kind"] == "LEFT":
        placement, term = "on", relation["on"]
    elif reply["where"] is not None:
        placement, term = "where", reply["where"]
    else:
        placement, term = "projection", reply["columns"][0][0]
    arity = 2 if term[0] == DISTINCT else 3
    mode = RENDER_MODE[term[0]][tuple(term[1:-arity])]
    return placement, mode, *(LEAVES[operand[0]] for operand in term[-arity:])


def analyzed(tag):
    left = table(LEFT)
    once = lambda term: query(tag, left, [[term, "answer"]])
    yield f"SELECT low IS DISTINCT FROM CAST(NULL AS bigint) AS answer FROM {L}", once(predicate("distinct", W, N))
    both = f"{L} a CROSS JOIN {R} b"
    yield (f"SELECT a.id IS NOT DISTINCT FROM b.high AS answer,a.id AS left_id,b.id AS right_id FROM {both}",
           scenario(tag, "projection", predicate("not-distinct", I, X)))
    yield f"SELECT low BETWEEN ASYMMETRIC id AND 2147483649 AS answer FROM {L}", once(predicate("asymmetric", W, I, wide(2147483649)))
    yield f"SELECT low NOT BETWEEN SYMMETRIC CAST(NULL AS bigint) AND high AS answer FROM {L}", once(
        predicate("not-symmetric", W, N, field(LEFT, 0, "high")))
    yield f"SELECT id FROM {L} WHERE low IS NOT DISTINCT FROM NULL", query(tag, left, [[I, "id"]], predicate("not-distinct", W, NULL))
    yield (f"SELECT a.id AS left_id,b.id AS right_id FROM {L} a LEFT JOIN {R} b ON a.id BETWEEN b.high AND a.low",
           scenario(tag, "on", predicate("asymmetric", I, X, W)))
    yield f"SELECT NULL BETWEEN id AND low AS answer FROM {L}", once(predicate("asymmetric", NULL, I, W))
    yield (f"SELECT -9223372036854775808 BETWEEN -9223372036854775808 AND 9223372036854775807 AS answer FROM {L}",
           once(predicate("asymmetric", wide(MIN), wide(MIN), wide(MAX))))


def negatives(tag):
    yield query(tag, table(LEFT), [[predicate("distinct", ["wide-slot", 0, 0], N), "bad"]]), {
        "exception": DIAGNOSTIC, "message": "A tested predicate slot must belong to the SELECT source."}
    yield scenario(tag, "on", predicate("symmetric", I, ["wide-slot", 0, 2], W)), {
        "exception": DIAGNOSTIC, "message": "A predicate slot must belong to a visible input occurrence."}
    for sql, message in (
        (f"SELECT NULL BETWEEN NULL AND low AS answer FROM {L}", "Each range comparison requires a known signed operand."),
        (f"SELECT (id + 1) IS DISTINCT FROM low AS answer FROM {L}", "A signed comparison operand requires a pure signed integer or NULL."),
        (f"SELECT label IS DISTINCT FROM label AS answer FROM {L}", "A signed comparison operand must resolve to pg_catalog.int4, pg_catalog.int8 or NULL."),
    ):
        yield native_request(tag, sql), {"exception": DIAGNOSTIC, "message": message}


def seeded(tag, seed, size):
    rng = random.Random(seed)
    terms = (I, J, W, X, N, NULL, literal(-2147483648), literal(2147483647), wide(MIN), wide(MAX), wide(-2147483649))
    for index in range(size):
        mode = tuple(MODES)[index % len(MODES)]
        placement = ("projection", "where", "on")[(index // len(MODES) + rng.randrange(3)) % 3]
        arity = len(MODES[mode]) + 1
        term = predicate(mode, *rng.choices(terms, k=arity))
        if placement != "projection" and index % 4 == 1:
            term = ["logic", "AND", term, compare("<", I, wide(MAX))]
        fields = [[operand, rng.choice(("left", "right", '値 " signed'))] for operand in rng.choices((I, J, W, X), k=1 + rng.randrange(4))]
        width = len(fields) + (placement == "projection")
        order = [[position, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))]
                 for position in rng.sample(list(range(1, width + 1)), rng.randrange(width + 1))]
        yield scenario(tag, placement, term, fields=fields, order=order)


def shadow_case(tag):
    return {**query(tag, table(LEFT), [
        [predicate("not-distinct", literal(2), wide(2)), "same"],
        [predicate("asymmetric", literal(2), wide(2), wide(2)), "inside"],
    ]), "shadow": True}


def requests(_, tag, contract):
    scope = contract["finite"]
    index = 0
    for arity, family in ((2, "distinctness"), (3, "range")):
        for placement, form, roles in product(scope["placements"], scope[family], product(scope["roles"], repeat=arity)):
            case = finite(tag, placement, form, roles)
            yield f"finite/{index}", case, case, None
            index += 1
    inputs = list(analyzed(tag))
    require(len(inputs) == len(contract["analysis"]), "postgres-signed-range-analysis-count")
    for index, (sql, case) in enumerate(inputs):
        yield f"analyze/{index}", native_request(tag, sql), case, None
    for seed in contract["exploration"]["seeds"]:
        for index, case in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", case, case, None
    case = shadow_case(tag)
    yield "witness/shadowed-operators", case, case, None
    for index, (case, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", case, None, reason


SHADOW_SQL = "SELECT 2 = 2::bigint AS same, 2 >= 2::bigint AS lower, 2 <= 2::bigint AS upper"


def prepare(engine, db):
    fixture = prepare_rows(engine, db)
    shadow = db.request({"fixture": [
        *(f"DROP OPERATOR IF EXISTS public.{symbol} (integer, bigint)" for symbol in ("=", ">=", "<=")),
        "CREATE OR REPLACE FUNCTION public.ir_shadow_signed_width(integer, bigint) RETURNS boolean LANGUAGE SQL IMMUTABLE RETURN false",
        *(f"CREATE OPERATOR public.{symbol} (FUNCTION = public.ir_shadow_signed_width, LEFTARG = integer, RIGHTARG = bigint)"
          for symbol in ("=", ">=", "<=")),
    ]})
    return {"fixture": fixture, "shadow-fixture": shadow,
            "shadow": db.request({"sql": SHADOW_SQL, "shadowBuiltinOperators": True, "observePostgresTypeIds": True}),
            "restored": db.request({"sql": SHADOW_SQL, "observePostgresTypeIds": True})}


def check_preparation(engine, environment, response):
    check_rows(engine, environment, response["fixture"])
    same({"status": "fixture"}, response["shadow-fixture"])
    fields = [[name, "bool", 16] for name in ("same", "lower", "upper")]
    for name, value in (("shadow", False), ("restored", True)):
        same({"status": "rows", "fields": fields, "rows": [[value, value, value]]}, response[name])


def prediction(item):
    fields = []
    for term, name in item["columns"]:
        native = "bool" if term[0] in (DISTINCT, RANGE) else type_of(term)
        fields.append([name, native, OID[native]])
    data = [[evaluate(term, row) for term, _ in item["columns"]] for row in input_rows(item["source"])
            if evaluate(item["where"], row) is True]
    return result_window(fields, data, item["orderBy"], None)


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"), "postgres-signed-range-rejected", reply)
    require(graph(reply) == graph(item), "postgres-signed-range-graph", {"expected": item, "observed": reply, "sql": reply["sql"]})


def check_observation(item, actual, sql):
    match(prediction(item), actual, sql)


def observation(raw):
    return raw


def observer_request(item, sql):
    return {"sql": sql, "observePostgresTypeIds": True, **({"shadowBuiltinOperators": True} if item.get("shadow") else {})}


def required_forms(*_):
    return {"projection", "where", "on", "nested", "ordered", "min", "max", "read0", "read1",
            *MODES, *LEAVES.values(), "null", "wide-integer", "integer"}


def sample_forms(forms, reply, *_):
    relation = reply["source"]
    role = "on" if is_join(relation) and relation["kind"] == "LEFT" else "where" if reply["where"] else "projection"
    forms[role] += 1
    if reply["orderBy"]:
        forms["ordered"] += 1
    term = relation["on"] if role == "on" else reply["where"] if role == "where" else reply["columns"][0][0]
    if term[0] == "logic":
        forms["nested"] += 1
        term = term[2]
    arity = 2 if term[0] == DISTINCT else 3
    forms[RENDER_MODE[term[0]][tuple(term[1:-arity])]] += 1
    for operand in term[-arity:]:
        forms[LEAVES.get(operand[0], operand[0])] += 1
        if operand[0] in ("slot", "wide-slot"):
            forms[f"read{operand[2] if len(operand) == 3 else 0}"] += 1
        if operand == wide(MIN):
            forms["min"] += 1
        if operand == wide(MAX):
            forms["max"] += 1


def controls():
    item = finite("pg-17.2", "projection", "distinct", ("int8-field", "int8-null"))
    result = prediction(item)
    raw = {"status": "rows", "fields": result["fields"], "rows": [row for band in result["bands"] for row in band["members"]]}
    check_observation(item, raw, "typed signed predicate control")
    cases = {
        "nullable-distinctness": {**raw, "rows": [[None, *raw["rows"][0][1:]], *raw["rows"][1:]]},
        "changed-result-kind": {**raw, "fields": [["answer", "int4", 23], *raw["fields"][1:]]},
        "lost-null-join-row": {**raw, "rows": raw["rows"][:-1]},
    }
    observed = {}
    for name, actual in cases.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-signed-range-control-signal", name)
            observed[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-signed-range-control-accepted", name)
    return observed


def replay_fault(rule, record):
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    try:
        check_value(rule.item, record["mutantResponse"])
        same(prediction(rule.item), witness["expected"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-signed-range-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/"
    renderer = root + "SelectSql.php"
    joined = scenario(tag, "on", predicate("symmetric", I, X, W))
    nullable = scenario(tag, "projection", predicate("not-distinct", W, N))
    symmetric = scenario(tag, "projection", predicate("symmetric", I, wide(MAX), wide(MIN)))
    inclusive = scenario(tag, "projection", predicate("asymmetric", I, wide(2), wide(2)))
    sql, graph_case = next(analyzed(tag))
    default_equality = "$same = 'COALESCE(' . $equality . ', (' . $left . ' IS NULL AND ' . $right . ' IS NULL))';"
    return [
        RenderFault("read-wrong-range-occurrence", renderer, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)",
                    joined, "meaning-mismatch"),
        RenderFault("collapse-typed-bigint-null", root + "Predicate/IntegerAtomLowering.php",
                    "$wide ? new WideIntegerNull() : new NullLiteral()", "new NullLiteral()", graph_case,
                    "postgres-signed-range-graph", native_request(tag, sql)),
        RenderFault("remove-null-safe-signed-equality", renderer, default_equality, "$same = $equality;", nullable, "meaning-mismatch"),
        RenderFault("remove-symmetric-signed-alternative", renderer, "if ($range->symmetric)", "if (false)", symmetric, "meaning-mismatch"),
        RenderFault("exclude-signed-range-endpoint", renderer, "OPERATOR(pg_catalog.>=)", "OPERATOR(pg_catalog.>)", inclusive, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
