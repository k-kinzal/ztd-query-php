"""Compare ordered local VARCHAR slots and their native protocol metadata."""

from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_local_projection import expand_local
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_count import DECLARATIONS, EMPTY, HEADING, PRIMARY, ROWS, TYPES, ref, table
from run_server_mysql_varchar_presence import null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, query
from server_cases import observation
from server_compound_cases import leaf, legacy
from server_join_cases import rows, slot, truth, value
from server_local_projection_cases import base_definition, case
from server_truth_model import pad_space_distinct_rows
from sqlite_domain import RenderFault

LABEL = "mysql-local-varchar"
CONTRACT = "c856de0d9e4d1ec62f3fe93b32164154a5fa192b6eab2e3b095683ad7cf1e8d9"
OBSERVER = "mysql_cast_observer.php"
SOURCES = {"populated": PRIMARY, "empty": EMPTY}
METADATA = {"slot": [3, 63], "text-slot": [253, 46]}
HEADING_ORDER = [2, 0, 1]


def specifications():
    path, profile = TOOL / "mysql-local-varchar-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-local-varchar-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeProtocolCollations": True}


def prepare(_engine, db):
    data = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in ROWS[PRIMARY])
    legacy_ = db.environment["database"][0].startswith("5.6.")
    settings = [] if legacy_ else ["SET SESSION optimizer_switch = 'derived_merge=on'"]
    fixture = db.request({"fixture": [*settings, *(f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in ROWS),
                                     *DECLARATIONS, f"INSERT INTO {SCHEMA}.{PRIMARY} VALUES {data}"]})
    calibration_sql = f"SELECT {', '.join(HEADING)} FROM {SCHEMA}.{PRIMARY}"
    converted = observation(db.request(observer_request(None, calibration_sql)))
    conversion = db.request({"fixture": ["SET SESSION character_set_results = NULL"]})
    measurement_profile = db.request({"sql": "SELECT @@character_set_client, @@character_set_connection, @@character_set_results"})
    read = {name: observation(db.request(observer_request(None, f"SELECT {', '.join(HEADING)} FROM {SCHEMA}.{name}"))) for name in ROWS}
    collations = db.request({"sql": "SELECT COLLATION_NAME, ID FROM information_schema.COLLATIONS "
                                    f"WHERE COLLATION_NAME IN ('binary', '{COLLATION}') ORDER BY ID"})
    layout = db.request({"sql": "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME "
                                "FROM information_schema.COLUMNS "
                                f"WHERE TABLE_SCHEMA='{SCHEMA}' AND TABLE_NAME IN ('{PRIMARY}', '{EMPTY}') "
                                "ORDER BY TABLE_NAME, ORDINAL_POSITION"})
    optimizer = None if legacy_ else db.request({"sql": "SELECT @@SESSION.optimizer_switch AS setting"})
    return {"fixture": fixture, "values": read, "collations": collations["rows"], "layout": layout["rows"], "optimizer": optimizer,
            "converted": {"sql": calibration_sql, "observed": converted}, "conversion": conversion, "measurementProfile": measurement_profile}


def check_preparation(engine, environment, actual):
    require(engine == "mysql", "mysql-local-varchar-engine", engine)
    same({"status": "fixture"}, actual["fixture"])
    same({"status": "fixture"}, actual["conversion"])
    same([["utf8mb4", "utf8mb4", None]], actual["measurementProfile"]["rows"])
    same([[COLLATION, 46], ["binary", 63]], actual["collations"])
    fields = [[name, *METADATA["slot" if kind == "int" else "text-slot"]] for name, kind in zip(HEADING, TYPES)]
    conversion_id = 45 if environment["database"][0].startswith(("5.6.", "5.7.")) else 255
    converted_fields = [[name, wire_type, conversion_id if collation == 46 else collation] for name, wire_type, collation in fields]
    same(observation({"status": "rows", "fields": converted_fields, "rows": ROWS[PRIMARY]}), actual["converted"]["observed"])
    for name, data in ROWS.items():
        same(observation({"status": "rows", "fields": fields, "rows": data}), actual["values"][name])
    layout = [[table_name, field, "int", None, None] if kind == "int" else [table_name, field, "varchar", kind[1], COLLATION]
              for table_name, (field, kind) in product(sorted(ROWS), zip(HEADING, TYPES))]
    same(layout, actual["layout"])
    if environment["database"][0].startswith("5.6."):
        same(None, actual["optimizer"])
    else:
        optimizer = actual["optimizer"]
        require(optimizer["status"] == "rows" and "derived_merge=on" in optimizer["rows"][0][0].split(","),
                "mysql-local-varchar-optimizer", optimizer)


def local(tag, definition, ordinals, names=None):
    return case("mysql", tag, definition, ordinals, names)


def base(tag, source, distinct=False, predicate=None):
    columns = [[slot(1), "bucket"], [ref("label"), "message"], [ref("empty_value"), "edge"]]
    selected = leaf("mysql", tag, columns, table(source), predicate)
    return {**selected, "distinct": distinct}


def finite(tag, depth, source, layout, distinct):
    inner = base(tag, SOURCES[source], distinct)
    for _ in range(depth - 1):
        inner = local(tag, inner, HEADING_ORDER)
    return local(tag, inner, layout)


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    result = {(depth, name, tuple(layout), distinct) for depth in b["depths"] for name in b["sources"]
              for layout in b["outputs"] for distinct in b["distinct"]}
    require(len(result) == b["identities"], "mysql-local-varchar-finite-size", len(result))
    return result


def finite_id(_, actual, _item):
    node = actual["local"]
    leaf_, depth = base_definition(node)
    subquery = node["definition"]
    while "local" in subquery:
        layer = subquery["local"]
        same(HEADING_ORDER, [ordinal for ordinal, _ in layer["columns"]])
        subquery = layer["definition"]
    return (depth, {name: role for role, name in SOURCES.items()}[leaf_["source"]["table"]],
            tuple(ordinal for ordinal, _ in node["columns"]), leaf_["distinct"])


def analyzed(tag):
    target = f"{SCHEMA}.{PRIMARY}"
    body = f"SELECT bucket, label AS message, empty_value AS edge FROM {target}"
    direct = base(tag, PRIMARY)
    yield (f"SELECT q.message AS r0, message AS r1 FROM ({body}) AS q", local(tag, direct, [1, 1]))
    yield (f"SELECT second_scope.r2 AS r0, second_scope.r0 AS r1 FROM "
           f"(SELECT first_scope.edge AS r0, first_scope.bucket AS r1, first_scope.message AS r2 FROM ({body}) AS first_scope) AS second_scope",
           finite(tag, 2, "populated", [2, 0], False))
    outer = f"SELECT {EMPTY}.message AS r0, edge AS r1 FROM "
    shadow = (outer + f"({body}) AS {EMPTY}" if legacy(tag)
              else f"WITH {EMPTY} AS ({body}) " + outer + EMPTY)
    yield shadow, local(tag, direct, [1, 2])
    yield (f"SELECT q.message AS r0 FROM (SELECT DISTINCT bucket, label AS message, empty_value AS edge FROM {target}) AS q",
           local(tag, base(tag, PRIMARY, True), [1]))


def negatives(tag):
    start = base(tag, PRIMARY)
    yield local(tag, start, [3]), "The local slot must belong to the definition."
    yield local(tag, local(tag, start, [0]), [1]), "The local slot must belong to the definition."
    yield local(tag, {**start, "limit": [1, 0]}, [1]), "The local definition requires an unbounded projection without ordering."
    yield finite(tag, 17, "populated", [1], False), "A local projection has at most sixteen definition boundaries."
    statement = "SELECT q.bucket+1 AS v FROM (SELECT bucket, label FROM " + SCHEMA + "." + PRIMARY + ") AS q"
    yield query(tag, statement, DECLARATIONS), "Each local result must read a resolved local slot."


def seeded(tag, seed, budget):
    rng = Random(seed)
    for index in range(budget):
        source = EMPTY if index % 4 == 0 else PRIMARY
        options = [slot(1), ref("label"), ref("alternate"), ref("empty_value")]
        columns = [[term, "item"] for term in rng.sample(options, index % 4 + 1)]
        selected = leaf("mysql", tag, columns, table(source), null_test(ref("label"), True) if index % 3 else None)
        selected["distinct"] = bool(rng.getrandbits(1))
        width = len(columns)
        for _ in range((1, 2, 4, 8, 16)[index % 5]):
            positions = [rng.randrange(width) for _ in range(index % 4 + 1)]
            selected = local(tag, selected, positions, ["duplicate"] * len(positions))
            width = len(positions)
        yield selected


def requests(_, tag, spec):
    b = spec["finite"]
    for index, vector in enumerate(product(b["depths"], b["sources"], b["outputs"], b["distinct"])):
        item = finite(tag, *vector)
        yield f"finite/{index}", item, item, None
    analyses = list(analyzed(tag))
    require(len(analyses) == len(spec["analysis"]), "mysql-local-varchar-analysis-incomplete")
    for index, (sql, item) in enumerate(analyses):
        yield f"analysis/{index}", query(tag, sql, DECLARATIONS), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def typed_term(term, source):
    role = term[0]
    if role == "null-test":
        return role, term[1], typed_term(term[2], source)
    field_name = source["columns"][term[1]]
    kind = source.get("columnTypes", ["int"] * len(source["columns"]))[term[1]]
    require(kind == TYPES[HEADING.index(field_name)] and role == ("slot" if kind == "int" else "text-slot")
            and (len(term) == 2 or term[2] == 0), "mysql-local-varchar-field-role", [term, source])
    return role, field_name


def graph(item):
    if "local" in item:
        node = item["local"]
        return "local", node["columns"], graph(node["definition"])
    source = item["source"]
    require(item["orderBy"] == [] and item["limit"] is None, "mysql-local-varchar-leaf-extent", item)
    return (source["schema"], source["table"], [(typed_term(term, source), label) for term, label in item["columns"]],
            None if item["where"] is None else typed_term(item["where"], source), item["distinct"])


def check_value(item, observed):
    require(observed["status"] == "constructed" and observed["profile"].startswith(item["release"] + "/")
            and observed["profile"].endswith("/materialized" if item["release"].startswith("mysql-5.6.") else "/derived-merge=on"),
            "mysql-local-varchar-positive-rejected", observed)
    same(graph(item), graph(expand_local(observed)))


def expected(item):
    layers = []
    leaf_ = item
    while "local" in leaf_:
        node = leaf_["local"]
        layers.append(node["columns"])
        leaf_ = node["definition"]

    def fixture(source):
        require(source["schema"] == SCHEMA, "mysql-local-varchar-source-schema", source)
        return HEADING, ROWS[source["table"]]

    fields = [[label, *METADATA[term[0]]] for term, label in leaf_["columns"]]
    projected = [[value(term, row, "mysql") for term, _ in leaf_["columns"]]
                 for row in rows(leaf_["source"], "mysql", table_rows=fixture) if truth(leaf_["where"], row, "mysql")]
    permitted = pad_space_distinct_rows(projected) if leaf_["distinct"] else [projected]
    for layout in reversed(layers):
        fields = [[name, *fields[ordinal][1:]] for ordinal, name in layout]
    outcomes = {}
    for candidate in permitted:
        for layout in reversed(layers):
            candidate = [[row[ordinal] for ordinal, _ in layout] for row in candidate]
        defined = observation({"status": "rows", "fields": fields, "rows": candidate})
        outcomes[canonical(defined)] = defined
    return [outcomes[key] for key in sorted(outcomes)]


def check_observation(item, actual, sql):
    predicted = expected(item)
    witness = {"sql": sql, "expected": predicted, "observed": actual}
    require(actual["status"] == "rows", "mysql-local-varchar-native-rejection", witness)
    require(canonical(actual) in {canonical(result) for result in predicted}, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    actual = witness["observed"]
    require(actual["status"] == "rows" and actual not in witness["expected"], "mysql-local-varchar-fault-evidence", witness)


def sample_forms(forms, observed, *_):
    leaf_, depth = base_definition(observed["local"])
    forms[f"depth/{depth}"] += 1
    forms[f"distinct/{leaf_['distinct']}"] += 1
    forms[f"filter/{leaf_['where'] is not None}"] += 1
    forms["table/" + leaf_["source"]["table"]] += 1
    outer = observed["local"]["columns"]
    ordinals = [ordinal for ordinal, _ in outer]
    if ordinals != sorted(ordinals):
        forms["ordinal/reordered"] += 1
    if len(set(ordinals)) != len(ordinals):
        forms["ordinal/repeated"] += 1
    if len({label for _, label in outer}) != len(outer):
        forms["label/repeated"] += 1
    for term, _ in leaf_["columns"]:
        forms["field/" + typed_term(term, leaf_["source"])[1]] += 1


def required_forms(*_):
    return {"depth/1", "depth/2", "depth/16", "distinct/True", "distinct/False", "filter/True", "filter/False",
            "table/" + PRIMARY, "table/" + EMPTY, "ordinal/reordered", "ordinal/repeated", "label/repeated",
            *("field/" + name for name in HEADING)}


def controls():
    item = finite("mysql-9.1.0", 16, "empty", [1, 1], False)
    good, = expected(item)
    check_observation(item, good, "known local VARCHAR metadata with no rows")
    altered = {**good, "fields": [[good["fields"][0][0], 253, 45], good["fields"][1]]}
    try:
        check_observation(item, altered, "change the empty result's collation ID")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-local-varchar-control-signal", error.detail)
    else:
        raise Failure("mysql-local-varchar-comparator-accepted")
    return {"input": item, "expected": good, "mismatch": altered}


def faults(_, tag):
    local_renderer = "sql-semantics-mysql/src/Ir/Rows/LocalProjectionSql.php"
    select_renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    return [
        RenderFault("change-local-varchar-slot", local_renderer, "$renderer->localColumn($column->slot, $inherited)", "$renderer->localColumn(0, $inherited)",
                    finite(tag, 1, "populated", [2, 0], False), "meaning-mismatch"),
        RenderFault("remove-repeated-local-varchar-rows", local_renderer, "return 'SELECT '", "return 'SELECT DISTINCT '",
                    finite(tag, 1, "populated", [1, 1], False), "meaning-mismatch"),
        RenderFault("bypass-reordered-local-varchar-layer", local_renderer, "$this->render($source, true)", "$renderer->render($source->definition, true)",
                    finite(tag, 2, "populated", [2, 0], False), "meaning-mismatch"),
        RenderFault("omit-local-varchar-leaf-distinct", select_renderer, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '", "'SELECT '",
                    finite(tag, 1, "populated", [1, 1], True), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
