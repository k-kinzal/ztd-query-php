"""Compare native VARCHAR INSERT SELECT with independent positional write effects."""

from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from mysql_varchar_storage import normalize_diagnostics, truncated
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_explicit_text_collation import collate
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_insert import CAST, HEADING, TYPE, WIDTH
from run_server_mysql_varchar_presence import null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, query
from server_compound_cases import leaf
from server_delete_cases import literal
from server_join_cases import join, slot
from server_truth_model import admits, pad_space_distinct_rows
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-insert-query"
CONTRACT = "3051f65adb18ad41c371f732ede4c66f1e6cee50246e9a6455c68b9bedda2b75"
TARGET = "ir_varchar_query_target"
SCAN = "ir_varchar_query_scan"
EMPTY = "ir_varchar_query_empty"
CONTROL = "ir_varchar_query_control"
EVENTS = "ir_varchar_query_events"
INPUT_COLUMNS = ("label", "bucket", "alternate")
INPUT_TYPES = (["varchar", 8, COLLATION], "int", ["varchar", 8, COLLATION])
DATA = (("a  ", 7, "é𐐷z"), ("a ", 7, "é𐐷z"), ("a ", 7, "é𐐷z"), ("B\x00Q", 8, "Z"),
        (None, 9, "wide"), (None, 10, None), ("𐐷", 11, "alt  "))
DEFAULTS = (83, "N", "R", "")
INITIAL = {"rows": [[-100, "x", "Q", ""]], "events": [], "scan": list(map(list, DATA)), "empty": [],
           "control": [[999, None, "C", None]]}
LAYOUTS = {"required": ("required_value",), "signed-required": ("row_key", "required_value"),
           "required-signed": ("required_value", "row_key"), "nullable-zero": ("nullable_value", "empty_value")}
STORAGE = (f"row_key INT DEFAULT 83, nullable_value VARCHAR(2) {CAST} DEFAULT 'N', "
           f"required_value VARCHAR(2) {CAST} NOT NULL DEFAULT 'R', empty_value VARCHAR(0) {CAST} DEFAULT ''")


def relation(name):
    return f"{SCHEMA}.{name}"


def definition(name):
    if name in (SCAN, EMPTY):
        columns = f"label VARCHAR(8) {CAST}, bucket INT, alternate VARCHAR(8) {CAST}"
    else:
        columns = STORAGE if name != EVENTS else "ordinal INT NOT NULL, " + STORAGE
    return f"CREATE TABLE {relation(name)} ({columns}) ENGINE=InnoDB"


def specifications():
    path, profile = TOOL / "mysql-varchar-insert-query-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-insert-query-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeMysqlVarcharInsertQuery": True}


def prepare(_engine, db):
    def values(rows):
        return ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in rows)

    names = (TARGET, SCAN, EMPTY, CONTROL, EVENTS)
    fields = ", ".join("NEW." + col for col in HEADING)
    fixture = db.request({"fixture": [
        "SET SESSION sql_notes = 1, max_error_count = 64, lc_messages = 'en_US'",
        *(f"DROP TABLE IF EXISTS {relation(name)}" for name in names), *(definition(name) for name in names),
        f"INSERT INTO {relation(TARGET)} VALUES {values(INITIAL['rows'])}",
        f"INSERT INTO {relation(CONTROL)} VALUES {values(INITIAL['control'])}",
        f"INSERT INTO {relation(SCAN)} VALUES {values(DATA)}",
        f"CREATE TRIGGER {relation(TARGET)}_observe AFTER INSERT ON {relation(TARGET)} FOR EACH ROW INSERT INTO {relation(EVENTS)} "
        f"SELECT COALESCE(MAX(ordinal), 0) + 1, {fields} FROM {relation(EVENTS)}",
    ]})
    prefix = f"INSERT INTO {relation(TARGET)} (required_value) SELECT "
    denied = [f"INSERT INTO {relation(CONTROL)} (required_value) SELECT 'a' AS p0", prefix + "RAND() AS p0",
              prefix + f"label AS p0 FROM {relation(TARGET)}", prefix + "'a' AS p0; DELETE FROM " + relation(TARGET)]
    recorded = {sql: db.request(observer_request(None, sql)) for sql in denied}
    native = prefix + f"NULL AS p0 FROM {relation(EMPTY)}"
    batch = f" AS p0 FROM {relation(SCAN)} WHERE bucket = 7"
    anchors = {"literal": prefix + "'a  ' AS p0", "field": prefix + "label" + batch,
               "explicit-field": prefix + "(label COLLATE utf8mb4_bin)" + batch}
    return {"fixture": fixture, "guard": recorded, "query": {"sql": native, "observed": db.request(observer_request(None, native))},
            "copyCalibration": {name: {"sql": sql, "observed": db.request(observer_request(None, sql))} for name, sql in anchors.items()}}


def check_preparation(engine, _environment, recorded):
    require(engine == "mysql", "mysql-varchar-insert-query-engine", engine)
    same({"status": "fixture"}, recorded["fixture"])
    require(len(recorded["guard"]) == 4, "mysql-varchar-insert-query-guard-count")
    for response in recorded["guard"].values():
        same({"status": "outside-observer-scope"}, response)
    initial = normalized_state(INITIAL)
    same({"status": "mutation", "count": 0, "columns": 0, "before": initial, "after": initial, "restored": initial,
          "warningCount": 0, "diagnostics": []}, normalized_effect(recorded["query"]["observed"]))
    for name, count, severity in (("literal", 1, "Note"), ("field", 3, "Warning"), ("explicit-field", 3, "Note")):
        observation = normalized_effect(recorded["copyCalibration"][name]["observed"])
        same(["mutation", count, 1, [[severity, 1265, "required_value"]]],
             [observation["status"], observation["count"], observation["warningCount"], observation["diagnostics"]])


def table(name):
    return {"schema": SCHEMA, "table": name, "columns": list(INPUT_COLUMNS), "columnTypes": list(INPUT_TYPES)}


def insert(tag, heading, columns, source=None, where=None, distinct=False):
    target = {"schema": SCHEMA, "table": TARGET, "columns": list(heading), "columnTypes": [TYPE[name] for name in heading]}
    selected = leaf("mysql", tag, columns, source, where)
    return {"dialect": "mysql", "release": tag, "target": target, "insertQuery": {**selected, "distinct": distinct}}


def finite(tag, layout, origin, mode, distinct):
    heading = LAYOUTS[layout]
    source = table(SCAN) if origin == "table" else None
    bound = {"row_key": slot(1), "required_value": field(0), "nullable_value": field(2), "empty_value": field(0)}
    constants = {name: literal(5) if name == "row_key" else ["null"] if mode == "null" else text("é𐐷z") for name in HEADING}
    columns = [[(bound if source else constants)[name], f"p{index}"] for index, name in enumerate(heading)]
    condition = ["boolean", False] if mode == "no-row" else null_test(field(0)) if source and mode == "null" else None
    return insert(tag, heading, columns, source, condition, distinct)


def structural_term(node, source):
    if node is None:
        return None
    role, *parts = node
    if role in ("slot", "text-slot"):
        name = source["columns"][parts[0]]
        kind = INPUT_TYPES[INPUT_COLUMNS.index(name)]
        layout = source.get("columnTypes", ["int"] * len(source["columns"]))
        require(layout[parts[0]] == kind and (len(parts) == 1 or parts[1] == 0)
                and role == ("slot" if kind == "int" else "text-slot"), "mysql-varchar-insert-query-address", [node, source])
        return role, name
    if role == "collated-text":
        return role, parts[0], structural_term(parts[1], source)
    if role == "null-test":
        return role, parts[0], structural_term(parts[1], source)
    if role == "text-comparison":
        return role, parts[0], parts[1], structural_term(parts[2], source), structural_term(parts[3], source)
    require(role in ("integer", "null", "text", "boolean"), "mysql-varchar-insert-query-expression-role", node)
    return tuple(node)


def graph(item):
    select = item["insertQuery"]
    source = select["source"]
    require(select["orderBy"] == [] and select["limit"] is None, "mysql-varchar-insert-query-extent", select)
    return (None if source is None else (source["schema"], source["table"]),
            tuple((structural_term(term, source), label) for term, label in select["columns"]),
            structural_term(select["where"], source), select["distinct"])


def check_value(item, actual):
    require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/"),
            "mysql-varchar-insert-query-positive-rejected", actual)
    target = actual["target"]
    same(item["target"], {"columnTypes": ["int"] * len(target["columns"]), **target})
    same(graph(item), graph(actual))


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    result = {(layout, origin, mode, distinct) for layout in b["layouts"] for origin in b["origins"] for mode in b["modes"]
              for distinct in b["distinct"]}
    require(len(result) == b["identities"], "mysql-varchar-insert-query-finite-size", len(result))
    return result


def finite_id(_, actual, _item):
    source, columns, where, distinct = graph(actual)
    heading = {columns: name for name, columns in LAYOUTS.items()}[tuple(actual["target"]["columns"])]
    mode = "no-row" if where == ("boolean", False) else "null" if where or any(term == ("null",) for term, _ in columns) else "all"
    return heading, "table" if source else "constant", mode, distinct


def analyzed(tag):
    start, scan, empty = "INSERT INTO " + relation(TARGET), relation(SCAN), relation(EMPTY)
    yield f"{start} (required_value) SELECT NULL AS p0", finite(tag, "required", "constant", "null", False)
    yield (f"{start} (row_key, required_value) SELECT r.bucket AS p0, r.label AS p1 FROM {scan} AS r",
           finite(tag, "signed-required", "table", "all", False))
    yield (f"{start} (required_value, row_key) SELECT DISTINCT label AS p0, bucket AS p1 FROM {scan}",
           finite(tag, "required-signed", "table", "all", True))
    yield (f"{start} (required_value) SELECT label AS p0 FROM {scan} WHERE label IS NULL",
           finite(tag, "required", "table", "null", False))
    yield (f"{start} (required_value) SELECT label AS p0 FROM {empty}",
           insert(tag, ("required_value",), [[field(0), "p0"]], table(EMPTY)))
    yield (f"{start} (empty_value) SELECT ('a ' COLLATE utf8mb4_bin) AS p0",
           insert(tag, ("empty_value",), [[collate(text("a ")), "p0"]]))


def negative(tag):
    text_boundary = "A query INSERT VARCHAR field requires a native text value or NULL."
    yield insert(tag, ("required_value",), [[literal(1), "p0"]]), text_boundary
    yield insert(tag, ("row_key",), [[text("a"), "p0"]]), "Each query INSERT field requires a signed value or NULL."
    yield insert(tag, ("required_value",), [[["parameter", 1], "p0"]]), text_boundary
    yield insert(tag, HEADING, [[literal(1), "p0"]]), "Each row of the INSERT query must match the target width."
    unordered = "The INSERT query must be independent, unbounded and unordered."
    yield insert(tag, ("required_value",), [[text("a"), "p0"]], join("CROSS", table(SCAN), table(EMPTY))), unordered
    item = insert(tag, ("required_value",), [[text("a"), "p0"]], table(SCAN))
    item["insertQuery"]["limit"] = [1, 0]
    yield item, unordered


def seeded(tag, seed, budget):
    rng = Random(seed)
    texts = ("a ", "é𐐷x", "", "b\x00c", "a'b", "a  ", "\t\n", "\u00a0\u00a0\u00a0")
    for index in range(budget):
        heading = rng.sample(HEADING, index % 4 + 1)
        stored = index % 3 != 0
        source = table(EMPTY if index % 7 == 0 else SCAN) if stored else None
        results = []
        for position, name in enumerate(heading):
            if name == "row_key":
                term = rng.choice([literal(rng.randrange(-10, 12)), ["null"], *([slot(1)] if stored else [])])
            else:
                choices = [text(rng.choice(texts)), ["null"], *([field(0), field(2)] if stored else [])]
                term = rng.choice(choices)
                if term != ["null"] and index % 3 != 1:
                    term = collate(term)
            results.append([term, "value" if index % 2 else f"p{position}"])
        choices = [None, ["boolean", False], *([null_test(field(0)), comparison("=", field(0), text("a"))] if stored else [])]
        yield insert(tag, heading, results, source, rng.choice(choices), bool(rng.getrandbits(1)))


def requests(_, tag, specification):
    b = specification["finite"]
    for index, key in enumerate(product(b["layouts"], b["origins"], b["modes"], b["distinct"])):
        item = finite(tag, *key)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(specification["analysis"]), "mysql-varchar-insert-query-analysis-incomplete")
    for index, (sql, item) in enumerate(inputs):
        yield f"analysis/{index}", query(tag, sql, list(map(definition, (TARGET, SCAN, EMPTY)))), item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def allowed_input_rows(item):
    source, columns, _, distinct = graph(item)
    model = item["insertQuery"]

    def value(term, row):
        role, *content = term
        if role in ("slot", "text-slot"):
            return row[INPUT_COLUMNS.index(content[0])]
        if role == "collated-text":
            return value(content[1], row)
        return {"null": lambda: None, "text": lambda: content[0], "integer": lambda: int(content[0])}[role]()

    incoming = {None: [()], (SCHEMA, SCAN): DATA, (SCHEMA, EMPTY): ()}[source]
    projection = [tuple(value(term, row) for term, _ in columns) for row in incoming
                  if admits(model["where"], lambda term: value(structural_term(term, model["source"]), row))]
    if not distinct:
        return [projection]
    return pad_space_distinct_rows(projection)


def bag(rows):
    return sorted(rows, key=canonical)


def normalized_state(state):
    events = state["events"]
    require(all(type(entry[0]) is int and entry[0] == ordinal for ordinal, entry in enumerate(events, 1)),
            "mysql-varchar-insert-query-trigger-order", events)
    return {key: bag([row[1:] for row in rows] if key == "events" else rows) for key, rows in state.items()}


def normalized_effect(effect):
    if effect["status"] != "mutation":
        return effect
    return {**effect, **{step: normalized_state(effect[step]) for step in ("before", "after", "restored")},
            "diagnostics": normalize_diagnostics(effect["diagnostics"], WIDTH)}


def expected(item):
    initial = normalized_state(INITIAL)
    outcomes = {}
    heading = item["target"]["columns"]
    _, columns, _, distinct = graph(item)
    copies = [term[0] == "text-slot" or (distinct and term[0] == "collated-text" and term[2][0] == "text-slot") for term, _ in columns]
    for source in allowed_input_rows(item):
        written, diagnostics = [], []
        null_warned = False
        for values in source:
            row = dict(zip(HEADING, DEFAULTS))
            for destination, value, direct_copy in zip(heading, values, copies):
                if destination in WIDTH:
                    if destination == "required_value" and value is None:
                        value = ""
                        if not null_warned or item["release"] == "mysql-5.6.51":
                            diagnostics.append(["Warning", 1048, destination])
                        null_warned = True
                    value, severity = truncated(value, WIDTH[destination], fast_copy=direct_copy)
                    if severity:
                        diagnostics.append([severity, 1265, destination])
                row[destination] = value
            written.append([row[name] for name in HEADING])
        after = {**initial, "rows": bag([*INITIAL["rows"], *written]), "events": bag(written)}
        outcome = {"status": "mutation", "count": len(written), "columns": 0, "before": initial, "after": after,
                   "restored": initial, "warningCount": len(diagnostics), "diagnostics": sorted(diagnostics)}
        outcomes[canonical(outcome)] = outcome
    return [outcomes[key] for key in sorted(outcomes)]


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    permitted = expected(item)
    witness = {"sql": sql, "expected": permitted, "observed": actual}
    require(actual["status"] == "mutation", "mysql-varchar-insert-query-native-rejection", witness)
    require(canonical(normalized_effect(actual)) in {canonical(effect) for effect in permitted}, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    actual = witness["observed"]
    same(expected(item), witness["expected"])
    require(actual["status"] == "mutation" and canonical(normalized_effect(actual)) not in {canonical(effect) for effect in witness["expected"]},
            "mysql-varchar-insert-query-fault-evidence", witness)


def sample_forms(forms, observed, *_):
    source, columns, where, distinct = graph(observed)
    forms[f"width/{len(columns)}"] += 1
    forms[f"distinct/{distinct}"] += 1
    forms["source/" + (source[1] if source else "unit")] += 1
    forms["filter/" + (where[0] if where else "none")] += 1
    labels = [name for _, name in columns]
    if len(set(labels)) < len(labels):
        forms["label/repeated"] += 1
    for destination, ((role, *parts), _) in zip(observed["target"]["columns"], columns):
        forms["target/" + destination] += 1
        forms["expression/" + role] += 1
        if role == "collated-text":
            role, *parts = parts[1]
        if role == "text-slot":
            forms["read/" + parts[0]] += 1
        if role == "text" and any(ord(char) > 127 for char in parts[0]):
            forms["literal/unicode"] += 1
        if role == "text" and parts[0].endswith(" "):
            forms["literal/padding"] += 1


def required_forms(*_):
    return {*(f"width/{size}" for size in range(1, 5)), *("target/" + dest for dest in HEADING), "source/unit",
            "source/" + SCAN, "source/" + EMPTY, "distinct/True", "distinct/False", "label/repeated", "filter/none",
            "filter/boolean", "filter/null-test", "expression/slot", "expression/text-slot", "expression/collated-text",
            "expression/null", "read/label", "read/alternate", "literal/unicode", "literal/padding"}


def controls():
    item = finite("mysql-9.1.0", "required", "constant", "all", False)
    predicted, = expected(item)
    stored, = predicted["after"]["events"]
    actual = {**predicted, "after": {**predicted["after"], "events": [[1, *stored]]},
              "diagnostics": [["Warning", 1265, "Data truncated for column 'required_value' at row 1"]]}
    check_observation(item, actual, "register a one-row text projection and its trigger")
    changed = {**actual, "after": {**actual["after"], "events": []}}
    try:
        check_observation(item, changed, "omit the effect of a successful insert")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-varchar-insert-query-control-signal", error.detail)
    else:
        raise Failure("mysql-varchar-insert-query-comparator-accepted")
    return {"input": item, "expected": predicted, "matched": actual, "mismatch": changed}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    native = "$input->columns[$expression->slot]->quoted()"
    return [
        RenderFault("reverse-varchar-query-write-positions", "sql-semantics-mysql/src/Ir/Mutation/InsertQuery.php",
                    "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    finite(tag, "nullable-zero", "table", "all", False), "meaning-mismatch"),
        RenderFault("read-other-varchar-query-field", renderer, native, "$input->columns[$expression instanceof TextColumnReference ? 2 : $expression->slot]->quoted()",
                    finite(tag, "required", "table", "all", False), "meaning-mismatch"),
        RenderFault("lose-varchar-query-distinct", renderer, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '", "'SELECT '",
                    finite(tag, "required", "table", "all", True), "meaning-mismatch"),
        RenderFault("lose-varchar-query-filter", renderer, "if ($select->where !== null)", "if (false)",
                    finite(tag, "required", "table", "no-row", False), "meaning-mismatch"),
        RenderFault("replace-varchar-query-null", renderer, "$expression instanceof NullLiteral => 'NULL',", "$expression instanceof NullLiteral => \"''\",",
                    finite(tag, "required", "constant", "null", False), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
