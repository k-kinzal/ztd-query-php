"""Observe native PostgreSQL text casts, nullable values and inferred parameter OIDs."""

from itertools import product
import json
import random
import sys

from postgres_typed_parameter_cases import cast, ref, select, shape
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import observation
from sqlite_domain import RenderFault

LABEL = "postgres-text-conversion"
CONTRACT = "bf714d2c501421e925cf630bfedaf0279080bcd2d3bb7fb0537adb4375fc79e4"
OBSERVER = "postgres_typed_parameter_observer.php"
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"
OIDS = {"text": "25", "int4": "23"}
ATOMS = {
    "null": ["null"], "false": ["boolean", False], "true": ["boolean", True],
    "int4-min": ["integer", "-2147483648"], "int4-zero": ["integer", "0"], "int4-max": ["integer", "2147483647"],
    "int8-min": ["wide-integer", "-9223372036854775808"], "int8-max": ["wide-integer", "9223372036854775807"],
    "empty-text": ["text", ""], "quote-text": ["text", "a'値\\b"],
}
DISTINCT_BINDING = "second position"


def specifications():
    path, profile = TOOL / "postgres-text-conversion-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-conversion-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    return db.request({"sql": "SELECT CAST($1 AS text) AS a0; SELECT FALSE", "bindings": ["ignored"]})


def check_preparation(_, environment, result):
    same("PREPARE without a supplied input signature", environment["binding"])
    same({"status": "outside-observer-scope"}, result)


def observer_request(item, sql):
    return {"sql": sql, "bindings": item["bindings"], "allowConstantProjection": not item["bindings"]}


def literal_case(tag, name, width):
    return select(tag, [cast("text", ATOMS[name])] * width, [])


def parameter_case(tag, value, layout):
    first = ref(1, "text")
    if layout == "single":
        return select(tag, [first], [value])
    if layout == "repeated":
        return select(tag, [first, first], [value])
    return select(tag, [ref(2, "text"), first], [value, DISTINCT_BINDING])


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": []}


def analyzed(tag):
    yield "SELECT CAST(-17 AS pg_catalog.text) AS a0, FALSE::pg_catalog.text AS a1, CAST(NULL AS pg_catalog.text) AS a2", select(
        tag, [cast("text", ["integer", "-17"]), cast("text", ["boolean", False]), cast("text", ["null"])], [])
    yield "SELECT CAST(CAST('it''s' AS pg_catalog.text) AS pg_catalog.text) AS a0", select(tag, [cast("text", ["text", "it's"])], [])
    yield "SELECT $1::pg_catalog.text AS a0", parameter_case(tag, "値", "single")
    yield "SELECT $2::pg_catalog.text AS a0, $1::integer AS a1", select(tag, [ref(2, "text"), ref(1, "int4")], [8, "value"])
    yield "SELECT CAST($1 AS pg_catalog.text) AS a0, ($1)::pg_catalog.text AS a1", parameter_case(tag, "a'b", "repeated")


def negatives(tag):
    for item, message in (
        (select(tag, [ref(1, "text"), ref(1, "int4")], ["0"]), "one input type"),
        (select(tag, [ref(2, "text")], ["missing", "value"]), "contiguous bound slots"),
        (select(tag, [cast("int4", ["text", "17"])], []), "typed text operand requires a text conversion"),
    ):
        yield item, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def requests(_, tag, spec):
    b = spec["finite"]
    for index, keys in enumerate(product(b["literalInputs"], b["literalWidths"])):
        item = literal_case(tag, *keys)
        yield f"finite/literal/{index}", item, item, None
    for index, keys in enumerate(product(b["parameterInputs"], b["parameterLayouts"])):
        item = parameter_case(tag, *keys)
        yield f"finite/parameter/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) + 1 == len(spec["analysis"]), "postgres-text-conversion-analysis-incomplete")
    for index, (sql, item) in enumerate(cases):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    known_context = analysis_request(tag, "SELECT CAST($1 AS text) AS a0")
    known_context["declarations"] = ["CREATE TABLE public.ir_text_context (id INTEGER)"]
    yield f"analyze/{len(cases)}", known_context, parameter_case(tag, "値", "single"), None
    corpus = b["parameterInputs"] + ["\tquote'\n", "𐐷", "-42", "a" * 200]
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            bindings = rng.choices(corpus, k=rng.randrange(4)) if rng.getrandbits(1) else []
            if bindings:
                pending = [ref(i + 1, "text") for i in range(len(bindings))]
                pending.extend(rng.choices(pending, k=rng.randrange(3)))
                rng.shuffle(pending)
            else:
                pending = rng.choices(list(ATOMS.values()), k=rng.randint(1, 4))
            columns = []
            for value in pending:
                depth = rng.randrange(4) if bindings else rng.randint(1, 3)
                for _ in range(depth):
                    value = cast("text", value)
                columns.append(value)
            item = select(tag, columns, bindings)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (input_, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, None, boundary


def finite_id(_engine, reply, item):
    columns = reply["columns"]
    if not item["bindings"]:
        operand = columns[0][0][2]
        return "literal", next(name for name, value in ATOMS.items() if value == operand), len(columns)
    positions = [term[1] for term, _ in columns]
    layout = {(1,): "single", (1, 1): "repeated", (2, 1): "reversed"}[tuple(positions)]
    return "parameter", json.dumps(item["bindings"][0], ensure_ascii=False), layout


def finite_ids(_engine, spec, _tag):
    b = spec["finite"]
    ids = {("literal", value, width) for value in b["literalInputs"] for width in b["literalWidths"]}
    ids.update(("parameter", json.dumps(value, ensure_ascii=False), layout)
               for value in b["parameterInputs"] for layout in b["parameterLayouts"])
    require(len(ids) == b["identities"], "wrong-postgres-text-conversion-domain")
    return ids


def expected(item):
    roles = {}

    def evaluate(term):
        kind = term[0]
        if kind == "parameter":
            position, role = term[1], term[2] if len(term) == 3 else "int4"
            if position in roles:
                require(roles[position] == role, "postgres-text-conversion-oracle-binding-conflict")
            roles[position] = role
            return role, item["bindings"][position - 1]
        if kind == "builtin-cast":
            role, value = evaluate(term[2])
            require(term[1] == "text", "postgres-text-conversion-oracle-unhandled-cast", term)
            return "text", None if value is None else str(value).lower() if role == "bool" else str(value)
        if kind == "text":
            return "text", term[1]
        if kind == "boolean":
            return "bool", term[1]
        if kind == "integer":
            return "int4", int(term[1])
        if kind == "wide-integer":
            return "int8", int(term[1])
        require(kind == "null", "postgres-text-conversion-oracle-unhandled-atom", term)
        return "unknown", None

    evaluated = [(name, *evaluate(term)) for term, name in item["columns"]]
    require(len(item["bindings"]) == len(roles), "postgres-text-conversion-oracle-binding-width")
    return {"status": "rows", "inputOids": [OIDS[roles[slot]] for slot in range(1, len(roles) + 1)],
            "fields": [[name, kind] for name, kind, _ in evaluated],
            "rows": [[value for _, _, value in evaluated]]}


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-conversion-rejected", reply)
    require(shape(item) == shape(reply), "postgres-text-conversion-shape", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    model = expected(item)
    require(actual["status"] == "rows", "postgres-text-conversion-native-rejection", {"sql": sql, "actual": actual})
    require(model == actual, "meaning-mismatch", {"sql": sql, "expected": model, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-text-conversion-fault-native")


def sample_forms(forms, reply, *_):
    positions = []
    for column, _ in reply["columns"]:
        depth = 0
        while column[0] == "builtin-cast":
            depth += 1
            column = column[2]
        forms[f"input/{column[0]}"] += 1
        forms[f"depth/{depth}"] += 1
        if column[0] == "parameter":
            positions.append(column[1])
    if positions and len(set(positions)) < len(positions):
        forms["repeat"] += 1
    if len(set(positions)) > 1:
        forms["distinct"] += 1


def required_forms(*_):
    return {"input/parameter", "input/text", "input/integer", "input/wide-integer", "input/boolean", "input/null",
            "depth/0", "depth/1", "depth/2", "depth/3", "repeat", "distinct"}


def controls():
    item = parameter_case("pg-17.2", "expected value", "single")
    prediction = expected(item)
    check_observation(item, prediction, "known inferred text type")
    different = {**prediction, "inputOids": ["1043"]}
    try:
        check_observation(item, different, "changed input OID")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-conversion-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-conversion-comparator-accepted")
    return {"healthy": prediction, "changedInputOid": different}


def faults(_, tag):
    program = "sql-semantics-postgres/src/Ir/"
    renderer = program + "SelectSql.php"
    parameter = program + "Binding/Parameter.php"
    return [
        RenderFault("change-cast-output-native-type", renderer, "' AS pg_catalog.' . $expression->target->value . ')'",
                    "' AS pg_catalog.varchar)'", literal_case(tag, "int4-zero", 1), "meaning-mismatch"),
        RenderFault("empty-text-in-place-of-null", renderer, "$expression instanceof NullLiteral => 'NULL',",
                    '$expression instanceof NullLiteral => "CAST(\'\' AS pg_catalog.text)",',
                    literal_case(tag, "null", 1), "meaning-mismatch"),
        RenderFault("swap-two-text-marker-positions", parameter, "$this->position . ' AS", "(3 - $this->position) . ' AS",
                    parameter_case(tag, "a'b", "reversed"), "meaning-mismatch"),
        RenderFault("change-marker-input-oid-only", parameter,
                    "'CAST($' . $this->position . ' AS pg_catalog.' . $this->input->value . ')'",
                    "'CAST(CAST($' . $this->position . ' AS pg_catalog.varchar) AS pg_catalog.text)'",
                    parameter_case(tag, "same native output", "single"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
