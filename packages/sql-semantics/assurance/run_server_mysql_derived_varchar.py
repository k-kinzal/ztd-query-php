"""Compare native VARCHAR exports in checked MySQL derived joins."""

from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_presence import null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, query
from server_cases import observation
from server_compound_cases import leaf
from server_join_cases import case, compare, is_join, join, leaves, rows, slot, truth, value
from server_truth_model import pad_space_distinct_rows
from sqlite_domain import RenderFault

LABEL = "mysql-derived-varchar"
CONTRACT = "e03ccc6eaff57ef5fb6b6cbd6a5e79b00676a4f1737182c33d714532e852fc25"
LEFT = "ir_derived_varchar_left"
RIGHT = "ir_derived_varchar_right"
HEADING = ("id", "label", "note")
TYPES = ("int", ["varchar", 8, COLLATION], ["varchar", 8, COLLATION])
NATIVE = {"slot": "LONG", "text-slot": "VAR_STRING", "null-test": "LONGLONG"}
DATA = {
    LEFT: [(1, "a ", "L"), (1, "a", "L"), (1, "a", "L"), (2, "A", "left"), (3, None, "none"),
           (4, "a\x00", "n"), (5, "𐐷", "right?")],
    RIGHT: [(1, "a ", "R"), (1, "a  ", "R"), (2, "a", "right"), (3, None, "other"), (4, "a\x00", ""), (9, "z", None)],
}


def specifications():
    path, profile = TOOL / "mysql-derived-varchar-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-derived-varchar-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def ddl(name):
    return (f"CREATE TABLE {SCHEMA}.{name} (id INT, label VARCHAR(8) COLLATE utf8mb4_bin, "
            "note VARCHAR(8) COLLATE utf8mb4_bin) ENGINE=InnoDB")


def prepare(_engine, db):
    def insert(name):
        values = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in DATA[name])
        return f"INSERT INTO {SCHEMA}.{name} VALUES {values}"

    marker = db.request({"fixture": [*(f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in DATA),
                                      *map(ddl, DATA), *map(insert, DATA)]})
    values = {name: observation(db.request({"sql": f"SELECT id, label, note FROM {SCHEMA}.{name}"})) for name in DATA}
    types = db.request({"sql": "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME "
                               "FROM information_schema.COLUMNS "
                               f"WHERE TABLE_SCHEMA='{SCHEMA}' AND TABLE_NAME IN ('{LEFT}', '{RIGHT}') "
                               "ORDER BY TABLE_NAME, ORDINAL_POSITION"})
    return {"fixture": marker, "values": values, "types": types["rows"]}


def check_preparation(engine, _environment, actual):
    require(engine == "mysql", "mysql-derived-varchar-engine", engine)
    same({"status": "fixture"}, actual["fixture"])
    for name, data in DATA.items():
        same(observation({"status": "rows", "fields": [["id", "LONG"], ["label", "VAR_STRING"], ["note", "VAR_STRING"]],
                          "rows": data}), actual["values"][name])
    same([[name, field_name, "int" if kind == "int" else "varchar", None if kind == "int" else kind[1],
           None if kind == "int" else COLLATION] for name, (field_name, kind) in product(sorted(DATA), zip(HEADING, TYPES))], actual["types"])


def table(name):
    return {"schema": SCHEMA, "table": name, "columns": list(HEADING), "columnTypes": list(TYPES)}


def field_of(name, occurrence=0):
    return slot(0, occurrence) if name == "id" else field(HEADING.index(name), occurrence)


def definition(tag, name, heading, filtered=False, distinct=False):
    body = leaf("mysql", tag, [[field_of(name), alias] for name, alias in heading], table(name),
                null_test(field_of("label"), True) if filtered else None)
    return {"derived": {**body, "distinct": distinct}}


def mixed(tag, name, text_first=True, filtered=False):
    heading = [("label", "message"), ("id", "id")]
    return definition(tag, name, heading if text_first else heading[::-1], filtered)


def finite(tag, kind, side, order, selection):
    first, filtered = order == "text-first", selection == "only-nonnull"
    owner = 0 if side == "left" else 1
    left = mixed(tag, LEFT, first, filtered) if owner == 0 else table(LEFT)
    right = mixed(tag, RIGHT, first, filtered) if owner == 1 else table(RIGHT)
    signed, text_position = (1, 0) if first else (0, 1)
    keys = [slot(signed if owner == position else 0, position) for position in (0, 1)]
    source = join(kind.upper(), left, right, None if kind == "cross" else compare("=", *keys))
    text_read = field(text_position, owner)
    return case("mysql", tag, source, [[text_read, "result"], [slot(signed, owner), "derived_id"], [null_test(text_read), "absent"]])


def text_join(tag):
    derived = mixed(tag, RIGHT)
    condition = comparison("=", field_of("label"), field(0, 1))
    source = join("INNER", table(LEFT), derived, condition)
    return case("mysql", tag, source, [[field(0, 1), "result"], [slot(1, 1), "derived_id"]])


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    identities = {(kind, side, order, selection) for kind in b["joins"] for side in b["derivedSides"]
                  for order in b["orders"] for selection in b["filters"]}
    require(len(identities) == b["identities"], "mysql-derived-varchar-finite-size", len(identities))
    return identities


def finite_id(_, actual, _item):
    source = actual["source"]
    side, = [name for name in ("left", "right") if "derived" in source[name]]
    body = source[side]["derived"]
    roles = tuple(term[0] for term, _ in body["columns"])
    order = {("text-slot", "slot"): "text-first", ("slot", "text-slot"): "integer-first"}[roles]
    return source["kind"].lower(), side, order, "unfiltered" if body["where"] is None else "only-nonnull"


def analyzed(tag):
    lhs, rhs = f"{SCHEMA}.{LEFT}", f"{SCHEMA}.{RIGHT}"
    conventional = join("CROSS", table(LEFT), mixed(tag, RIGHT))
    yield (f"SELECT q.message AS `first_value`,message AS again FROM {lhs} l CROSS JOIN (SELECT label AS message,id FROM {rhs}) q",
           case("mysql", tag, conventional, [[field(0, 1), "first_value"], [field(0, 1), "again"]]))
    left = join("LEFT", table(LEFT), mixed(tag, RIGHT, filtered=True), compare("=", slot(0), slot(1, 1)))
    yield (f"SELECT q.message AS result FROM {lhs} l LEFT JOIN (SELECT label AS message,id FROM {rhs} WHERE label IS NOT NULL) q "
           "ON l.id=q.id WHERE q.message IS NULL", case("mysql", tag, left, [[field(0, 1), "result"]], null_test(field(0, 1))))
    yield (f"SELECT q.message AS result,q.id AS derived_id FROM {lhs} l JOIN (SELECT label AS message,id FROM {rhs}) q "
           "ON l.label=q.message", text_join(tag))
    yield (f"SELECT a.message AS a,b.message AS b FROM (SELECT label AS message,id FROM {lhs}) a "
           f"CROSS JOIN (SELECT label AS message,id FROM {rhs}) b",
           case("mysql", tag, join("CROSS", mixed(tag, LEFT), mixed(tag, RIGHT)), [[field(0, 0), "a"], [field(0, 1), "b"]]))


def negatives(tag):
    example = finite(tag, "cross", "right", "text-first", "unfiltered")
    yield {**example, "columns": [[slot(0, 1), "result"]]}, "Column slot must belong to the SELECT source."
    message = "A derived VARCHAR reference must read a native VARCHAR output."
    yield {**example, "columns": [[field(1, 1), "result"]]}, message
    yield {**example, "where": null_test(field(2, 1))}, message
    bad_on = join("LEFT", table(LEFT), mixed(tag, RIGHT), compare("=", slot(0), slot(0, 1)))
    yield {**example, "source": bad_on}, "A predicate slot must belong to a visible input occurrence."
    computed = {"derived": leaf("mysql", tag, [[text("x"), "v"]])}
    yield {**example, "source": join("CROSS", table(LEFT), computed)}, "A derived join field requires a signed literal or a signed column, or a native VARCHAR column."


def seeded(tag, seed, budget):
    rng = Random(seed)
    for index in range(budget):
        derived = ((True, True), (True, False), (False, True))[index % 3]
        sources, keyed, text_reads = [], [], []
        for position, (name, local) in enumerate(zip(DATA, derived)):
            if not local:
                sources.append(table(name))
                keyed.append(slot(0, position))
                text_reads.append(field_of("label", position))
                continue
            heading = [("id", "id"), ("label", "message")] + [(rng.choice(("label", "note")), "message") for _ in range(index % 3 + 1)]
            rng.shuffle(heading)
            sources.append(definition(tag, name, heading, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))))
            keyed.append(slot(heading.index(("id", "id")), position))
            text_reads.append(field(heading.index(("label", "message")), position))
        kind = ("CROSS", "LEFT", "RIGHT", "INNER")[index % 4]
        on = None if kind == "CROSS" else comparison("=", *text_reads) if index % 3 else compare("=", *keyed)
        source = join(kind, *sources, on)
        fields = [[rng.choice([*text_reads, *keyed]), "same"] for _ in range(index % 4 + 1)]
        condition = null_test(rng.choice(text_reads), bool(rng.getrandbits(1))) if index % 3 == 1 else None
        yield case("mysql", tag, source, fields, condition)


def requests(_, tag, specification):
    b = specification["finite"]
    for index, vector in enumerate(product(b["joins"], b["derivedSides"], b["orders"], b["filters"])):
        item = finite(tag, *vector)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(analyzed(tag)):
        yield f"analysis/{index}", query(tag, sql, list(map(ddl, DATA))), item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": reason}


def expression(term, inputs):
    if term is None:
        return None
    role, *parts = term
    if role in ("slot", "text-slot"):
        position = parts[1] if len(parts) == 2 else 0
        owner = inputs[position]
        if "derived" in owner:
            ordinal = parts[0]
            kind = owner["derived"]["columns"][ordinal][0][0]
        else:
            layout = owner.get("columnTypes", ["int"] * len(owner["columns"]))
            ordinal = owner["columns"][parts[0]]
            require(layout[parts[0]] == TYPES[HEADING.index(ordinal)], "mysql-derived-varchar-physical-type", [term, owner])
            kind = "slot" if layout[parts[0]] == "int" else "text-slot"
        require(role == kind, "mysql-derived-varchar-output-type", [term, owner])
        return role, position, ordinal
    if role == "null-test":
        return role, parts[0], expression(parts[1], inputs)
    if role in ("comparison", "text-comparison"):
        return role, *parts[:-2], expression(parts[-2], inputs), expression(parts[-1], inputs)
    require(role in ("null", "boolean"), "mysql-derived-varchar-unknown-role", term)
    return tuple(term)


def graph(item):
    def origin(source):
        if "derived" in source:
            definition_ = source["derived"]
            require(definition_["orderBy"] == [] and definition_["limit"] is None, "mysql-derived-varchar-child-extent", definition_)
            return "derived", graph(definition_)
        if is_join(source):
            return source["kind"], origin(source["left"]), origin(source["right"]), expression(source["on"], leaves(source))
        return source["schema"], source["table"]

    source = item["source"]
    inputs = leaves(source)
    return (origin(source), tuple((expression(term, inputs), label) for term, label in item["columns"]),
            expression(item["where"], inputs), item.get("distinct", False))


def table_data(source):
    require(source["schema"] == SCHEMA, "mysql-derived-varchar-physical-schema", source)
    return HEADING, DATA[source["table"]]


def expected(item):
    def raw(query_, table_rows):
        return [[value(term, row, "mysql") for term, _ in query_["columns"]] for row in table_rows
                if truth(query_["where"], row, "mysql")]

    def evaluate_child(child):
        projected = raw(child, rows(child["source"], "mysql", table_rows=table_data))
        return pad_space_distinct_rows(projected) if child["distinct"] else [projected]

    definitions = [source["derived"] for source in leaves(item["source"]) if "derived" in source]
    fields = [[label, NATIVE[term[0]]] for term, label in item["columns"]]
    all_outcomes = {}
    for admitted in product(*(evaluate_child(child) for child in definitions)):
        values = {id(child): materialized for child, materialized in zip(definitions, admitted)}
        composed = raw(item, rows(item["source"], "mysql", lambda child: values[id(child)], table_data))
        native = [[int(cell) if type(cell) is bool else cell for cell in row] for row in composed]
        measured = observation({"status": "rows", "fields": fields, "rows": native})
        all_outcomes[canonical(measured)] = measured
    return [all_outcomes[key] for key in sorted(all_outcomes)]


def check_value(item, observed):
    require(observed["status"] == "constructed" and observed["profile"].startswith(item["release"] + "/"),
            "mysql-derived-varchar-positive-rejected", observed)
    same(graph(item), graph(observed))


def check_observation(item, actual, sql):
    predictions = expected(item)
    witness = {"sql": sql, "expected": predictions, "observed": actual}
    require(actual["status"] == "rows", "mysql-derived-varchar-native-rejection", witness)
    require(canonical(actual) in {canonical(prediction) for prediction in predictions}, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    actual = witness["observed"]
    require(actual["status"] == "rows" and actual not in witness["expected"], "mysql-derived-varchar-fault-evidence", witness)


def sample_forms(forms, actual, *_):
    source = actual["source"]
    forms["join/" + source["kind"]] += 1
    if source["on"] is not None:
        forms["condition/" + source["on"][0]] += 1
    forms[f"outer-filter/{actual['where'] is not None}"] += 1
    derived = []
    for side, input_ in enumerate(leaves(source)):
        if "derived" in input_:
            child = input_["derived"]
            derived.append(child)
            forms[f"side/{side}"] += 1
            forms[f"distinct/{child['distinct']}"] += 1
            forms[f"filtered/{child['where'] is not None}"] += 1
            forms["first/" + child["columns"][0][0][0]] += 1
            fields = [label for _, label in child["columns"]]
            if len(set(fields)) < len(fields):
                forms["heading/repeated"] += 1
    forms[f"derived-count/{len(derived)}"] += 1


def required_forms(*_):
    return {"join/CROSS", "join/LEFT", "join/RIGHT", "join/INNER", "condition/comparison", "condition/text-comparison",
            "outer-filter/True", "outer-filter/False", "side/0", "side/1", "first/slot", "first/text-slot", "filtered/True",
            "filtered/False", "distinct/True", "distinct/False", "heading/repeated", "derived-count/1", "derived-count/2"}


def controls():
    item = finite("mysql-9.1.0", "left", "right", "text-first", "only-nonnull")
    good, = expected(item)
    check_observation(item, good, "known left join VARCHAR output")
    changed = {**good, "fields": [["result", "LONG"], *good["fields"][1:]]}
    try:
        check_observation(item, changed, "alter the derived field's native kind")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-derived-varchar-control-signal", error.detail)
    else:
        raise Failure("mysql-derived-varchar-comparator-accepted")
    return {"input": item, "expected": good, "mismatch": changed}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    dual = case("mysql", tag, join("CROSS", mixed(tag, LEFT), mixed(tag, RIGHT)), [[field(0, 1), "result"]])
    compare_sql = "$left . ' ' . $comparison->operator->value . ' ' . $right"
    return [
        RenderFault("read-other-derived-varchar-occurrence", renderer, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", dual, "meaning-mismatch"),
        RenderFault("read-adjacent-derived-varchar-position", renderer, "$this->localColumn($expression->slot,",
                    "$this->localColumn($expression instanceof TextColumnReference ? 1 : $expression->slot,",
                    finite(tag, "cross", "right", "text-first", "unfiltered"), "meaning-mismatch"),
        RenderFault("omit-derived-varchar-filter", renderer, "if ($select->where !== null)", "if (false)",
                    finite(tag, "left", "right", "text-first", "only-nonnull"), "meaning-mismatch"),
        RenderFault("change-derived-varchar-equality", renderer, compare_sql, "$left . ' <=> ' . $right", text_join(tag), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
