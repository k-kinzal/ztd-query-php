"""Compare PostgreSQL's stored signed operands and deferred default assignments."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_create_table import OBSERVER, check_value
from run_server_join import main
from run_server_postgres_create_text import (
    DIAGNOSTIC, TABLE, STORAGE, analysis, check_preparation as check_text_preparation, prepare, specifications as text_specifications,
)
from server_create_table_cases import case, expected as declaration_effect, observation
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "postgres-create-signed-defaults"
CONTRACT = "ab925fa9e5bd654cb8b6e9b20b4dcff3eeac415322e0398ec6dfd4087b8236a6"
RANGES = {"SMALLINT": (-(1 << 15), (1 << 15) - 1), "INTEGER": (-(1 << 31), (1 << 31) - 1),
          "BIGINT": (-(1 << 63), (1 << 63) - 1)}
CASTS = [["bigint", "integer", "a", "int4(bigint)"], ["bigint", "smallint", "a", "int2(bigint)"],
         ["integer", "bigint", "i", "int8(integer)"], ["integer", "smallint", "a", "int2(integer)"]]
FORMS = {
    "absent": None, "null": ["null"],
    "int4-lower": literal(-(1 << 31)), "int4-above-smallint": literal(1 << 15), "int4-zero": literal(0),
    "int8-minimum": ["wide-integer", str(-(1 << 63))], "int8-maximum": ["wide-integer", str((1 << 63) - 1)],
    "int8-small": ["wide-integer", "42"],
}


def specifications():
    path = TOOL / "postgres-create-signed-defaults-v1.json"
    require(digest(path) == CONTRACT, "postgres-create-signed-defaults-contract-mismatch")
    _, profiles = text_specifications()
    return json.loads(path.read_text()), profiles


def check_preparation(engine, environment, prepared):
    check_text_preparation(engine, environment, prepared)
    same(0, environment["int8TextCastOverrides"])
    same(CASTS, environment["signedAssignmentCasts"])


def finite_points(spec):
    b = spec["finite"]
    return product(b["destinations"], b["sources"], b["nullable"], b["ifNotExists"], b["occupied"])


def finite_ids(_, spec, _tag):
    points = set(finite_points(spec))
    require(len(points) == spec["finite"]["identities"] and set(FORMS) == set(spec["finite"]["sources"]),
            "postgres-create-signed-defaults-finite-size")
    return points


def finite_case(tag, destination, source, nullable=True, optional=False, occupied=False):
    return case("postgres", tag, [["c0", destination, nullable, FORMS[source]]], optional, occupied)


def finite_id(_, response, item):
    model = response["create"]
    [(name, destination, nullable, default)] = model["columns"]
    matching = [source for source, operand in FORMS.items() if operand == default]
    require(name == "c0" and len(matching) == 1, "postgres-create-signed-defaults-finite-operand", model)
    return destination, matching[0], nullable, model["ifNotExists"], item["occupied"]


def analyzed(tag):
    for kind, role, source in (
        ("BIGINT", "int8-minimum", "-9223372036854775808"),
        ("BIGINT", "int8-maximum", "9223372036854775807"),
        ("SMALLINT", "int4-above-smallint", "32768"),
        ("INTEGER", "int8-maximum", "9223372036854775807"),
        ("SMALLINT", "int8-small", "CAST(42 AS pg_catalog.int8)"),
        (STORAGE, "int8-minimum", "-9223372036854775808"),
    ):
        yield analysis(tag, f"CREATE TABLE {TABLE} (c0 {kind} DEFAULT {source})"), finite_case(tag, kind, role)
    fields = [["c1", STORAGE, False, FORMS["int8-maximum"]], ["c0", "SMALLINT", True, FORMS["int4-above-smallint"]]]
    yield analysis(tag, f"CREATE TABLE {TABLE} (c1 pg_catalog.text NOT NULL DEFAULT 9223372036854775807, c0 SMALLINT DEFAULT 32768)"), (
        case("postgres", tag, fields))


def negatives(tag):
    for value in (str(1 << 63), str(-(1 << 63) - 1)):
        yield case("postgres", tag, [["c0", "BIGINT", True, ["wide-integer", value]]]), "Integer is outside the signed 64-bit range."
    yield case("postgres", tag, [["c0", "INTEGER", True, ["text", "text value"]]]), "A text default requires native text storage."
    for expr in ("TRUE", "1 + 2"):
        yield analysis(tag, f"CREATE TABLE {TABLE} (c0 INTEGER DEFAULT {expr})"), "The column requires an integer, text or NULL default."


def seeded(tag, seed, count):
    rng = Random(seed)
    kinds = tuple(RANGES) + (STORAGE,)
    default_sources = tuple(FORMS.values()) + (["wide-integer", "2147483648"], ["wide-integer", "-2147483649"])
    for i in range(count):
        fields = []
        for position in range(rng.randint(1, 6)):
            kind = kinds[(i + position) % len(kinds)]
            default = rng.choice(default_sources)
            if kind == STORAGE and i % 2:
                default = ["text", rng.choice(("it's", "𐐷", ""))]
            fields.append([f"c{position}", kind, bool(rng.randrange(2)), default])
        rng.shuffle(fields)
        yield case("postgres", tag, fields, bool(i & 1), bool(i & 2))


def requests(_, tag, spec):
    for index, point in enumerate(finite_points(spec)):
        item = finite_case(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = tuple(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "postgres-create-signed-defaults-analysis-incomplete")
    for index, (query, meaning) in enumerate(cases):
        yield f"analysis/{index}", query, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def field_layout(item):
    return [["prior", "INTEGER", False, literal(97)]] if item["occupied"] else item["create"]["columns"]


def replacement_values(item):
    return {name: f"replacement {i} 𐐷" if storage == STORAGE else i + 7
            for i, (name, storage, _nullable, _default) in enumerate(field_layout(item))}


def observer_request(item, sql):
    return {"sql": sql, "occupied": item["occupied"], "explicitReplacement": replacement_values(item)}


def default_expression(default):
    if default is None or default == ["null"]:
        return None
    typ, value = default
    if typ == "text":
        return "'" + value.replace("'", "''") + "'::text"
    if typ == "integer":
        return f"'{value}'::integer" if int(value) < 0 else value
    require(typ == "wide-integer", "postgres-create-signed-defaults-source-kind", default)
    low, high = RANGES["INTEGER"]
    if low <= int(value) <= high:
        return f"({default_expression(['integer', value])})::bigint"
    return f"'{value}'::bigint"


def overflow(storage, default):
    if storage not in RANGES or default is None or default[0] not in {"integer", "wide-integer"}:
        return False
    low, high = RANGES[storage]
    return not low <= int(default[1]) <= high


def expected(item):
    result = declaration_effect(item)
    fields = field_layout(item)
    expressions = [default_expression(default) for _, _kind, _nullable, default in fields]
    for field in ("catalog", "afterRollback"):
        if result[field] is not None:
            result[field] = {**result[field], "defaultExpressions": expressions}
    result["stored"] = []
    result["explicitReplacement"] = None
    if result["status"] != "executed":
        return result
    result["explicitReplacement"] = list(replacement_values(item).values())
    if any(overflow(kind, default) for _, kind, _nullable, default in fields):
        result["defaultInsertion"] = {"status": "22003"}
    return result


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == prediction["status"], "postgres-create-signed-defaults-native-status", witness)
    try:
        same(prediction, actual)
    except Failure as failure:
        raise Failure(failure.signal, witness) from failure


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "executed" and witness["observed"] != witness["expected"],
            "postgres-create-signed-defaults-fault-native", witness)


def required_forms(*_):
    return {"role/int4", "role/int8", "role/null", "role/text", "role/absent", "overflow/True", "overflow/False",
            "required/True", "required/False", "width/single", "width/multiple", "occupied/True", "occupied/False",
            *("type/" + key for key in (*RANGES, STORAGE))}


def sample_forms(forms, response, _engine, item):
    fields = response["create"]["columns"]
    forms["width/" + ("single" if len(fields) == 1 else "multiple")] += 1
    forms[f"occupied/{item['occupied']}"] += 1
    for _, storage, nullable, default in fields:
        forms[f"type/{storage}"] += 1
        forms[f"required/{not nullable}"] += 1
        forms[f"overflow/{overflow(storage, default)}"] += 1
        raw = default[0] if default is not None else "absent"
        forms["role/" + {"integer": "int4", "wide-integer": "int8"}.get(raw, raw)] += 1


def controls():
    item = finite_case("pg-17.2", "SMALLINT", "int8-maximum")
    prediction = expected(item)
    check_observation(item, prediction, "deferred SMALLINT overflow")
    changed = {
        "misplace-assignment-failure": {**prediction, "defaultInsertion": {"status": "23502"}},
        "discard-explicit-replacement": {**prediction, "explicitReplacement": None},
    }
    detections = {}
    for name, observed in changed.items():
        try:
            check_observation(item, observed, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-create-signed-defaults-control-signal", failure.detail)
            detections[name] = failure.signal
        else:
            raise Failure("postgres-create-signed-defaults-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": changed, "detections": detections}


def faults(_, tag):
    base = "sql-semantics-postgres/src/Ir/Schema/"
    field = base + "TableColumn.php"
    small_wide = finite_case(tag, "BIGINT", "int8-small")
    wide = finite_case(tag, "BIGINT", "int8-minimum")
    narrow = finite_case(tag, "SMALLINT", "int8-maximum")
    mixed = case("postgres", tag, [["c0", STORAGE, False, FORMS["int8-maximum"]], ["c1", "BIGINT", True, FORMS["int8-minimum"]]])
    return [
        RenderFault("omit-postgres-int8-default-type", field, "' DEFAULT CAST(' . $this->default->decimal . ' AS pg_catalog.int8)'",
                    "' DEFAULT ' . $this->default->decimal", small_wide, "meaning-mismatch"),
        RenderFault("replace-postgres-bigint-default", field, "$this->default->decimal . ' AS pg_catalog.int8)'", "'0 AS pg_catalog.int8)'",
                    wide, "meaning-mismatch"),
        RenderFault("widen-postgres-default-destination", field, "$this->storage->value", "'BIGINT'", narrow, "meaning-mismatch"),
        RenderFault("reverse-postgres-signed-defaults", base + "CreateTable.php", "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    mixed, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
