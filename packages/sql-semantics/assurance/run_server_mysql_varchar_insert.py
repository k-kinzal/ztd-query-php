"""Compare typed MySQL VALUES against ordered native insert effects."""

from itertools import product
import json
from random import Random
import sys

from mysql_varchar_storage import normalize_diagnostics, truncated
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import text
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, query as analysis_query
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-insert"
CONTRACT = "2fd2869e86715f893955c800e4d63d60f45dd6b9b2ac42df3ab2f95cdbbddbc2"
NAME = "ir_varchar_insert"
TARGET = f"{SCHEMA}.{NAME}"
EVENTS = f"{TARGET}_events"
HEADING = ("row_key", "nullable_value", "required_value", "empty_value")
ORDER = {"natural": HEADING, "reordered": ("required_value", "empty_value", "row_key", "nullable_value")}
FIELDS = {"nullable": "nullable_value", "required": "required_value", "zero-capacity": "empty_value"}
WIDTH = {"nullable_value": 2, "required_value": 2, "empty_value": 0}
TYPE = {"row_key": "int", **{key: ["varchar", capacity, COLLATION, *([False] if key == "required_value" else [])]
                            for key, capacity in WIDTH.items()}}
BASELINE = ((1, "a ", "R", ""), (2, None, "𐐷", None))
DEFAULT = {"nullable_value": "N", "required_value": "R", "empty_value": ""}
VALUES = {"fitting": "x", "wide": "é𐐷z", "padding": "a  ", "ascii-whitespace": "\t\n",
          "unicode-whitespace": "\u00a0\u00a0\u00a0", "null": None}
CAST = "CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"
DECLARATION = f"CREATE TABLE {TARGET} (row_key INT NOT NULL PRIMARY KEY, nullable_value VARCHAR(2) {CAST}, " \
              f"required_value VARCHAR(2) {CAST} NOT NULL, empty_value VARCHAR(0) {CAST}) ENGINE=InnoDB"


def specifications():
    path, profile = TOOL / "mysql-varchar-insert-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-insert-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeMysqlVarcharInsert": True}


def prepare(_engine, db):
    heading = ", ".join(HEADING)
    data = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in BASELINE)
    receipt = db.request({"fixture": [
        "SET SESSION sql_notes = 1, max_error_count = 64, lc_messages = 'en_US'",
        f"DROP TABLE IF EXISTS {EVENTS}", f"DROP TABLE IF EXISTS {TARGET}", DECLARATION,
        f"INSERT INTO {TARGET} ({heading}) VALUES {data}",
        f"CREATE TABLE {EVENTS} (ordinal INTEGER NOT NULL, row_key INT, nullable_value VARCHAR(2) {CAST}, "
        f"required_value VARCHAR(2) {CAST}, empty_value VARCHAR(0) {CAST}) ENGINE=InnoDB",
        f"CREATE TRIGGER {TARGET}_observe AFTER INSERT ON {TARGET} FOR EACH ROW INSERT INTO {EVENTS} "
        f"SELECT COALESCE(MAX(ordinal), 0) + 1, NEW.row_key, NEW.nullable_value, NEW.required_value, NEW.empty_value FROM {EVENTS}",
    ]})
    invalid = [f"INSERT INTO {SCHEMA}.wrong (row_key) VALUES (123)",
               f"INSERT INTO {TARGET} (unknown) VALUES (1)",
               f"INSERT INTO {TARGET} (row_key) VALUES (1); DELETE FROM {TARGET}"]
    blocked = [db.request(observer_request(None, sql)) for sql in invalid]
    baseline = db.request({"sql": f"SELECT {heading} FROM {TARGET} ORDER BY row_key"})
    settings = db.request({"sql": "SELECT @@sql_notes, @@max_error_count, @@lc_messages"})
    triggers = db.request({"sql": "SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING FROM information_schema.TRIGGERS "
                                  f"WHERE TRIGGER_SCHEMA = '{SCHEMA}' AND EVENT_OBJECT_TABLE = '{NAME}'"})
    engines = db.request({"sql": "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES "
                                  f"WHERE TABLE_SCHEMA = '{SCHEMA}' AND TABLE_NAME IN ('{NAME}', '{NAME}_events') "
                                  "ORDER BY TABLE_NAME"})
    return {"fixture": receipt, "blocked": blocked, "baseline": baseline, "settings": settings["rows"],
            "triggers": triggers["rows"], "engines": engines["rows"]}


def check_preparation(engine, _environment, receipt):
    require(engine == "mysql", "mysql-varchar-insert-fixture-engine", engine)
    same({"status": "fixture"}, receipt["fixture"])
    same([{"status": "outside-observer-scope"}] * 3, receipt["blocked"])
    same(list(HEADING), [column[0] for column in receipt["baseline"]["fields"]])
    same([list(row) for row in BASELINE], receipt["baseline"]["rows"])
    same([[1, 64, "en_US"]], receipt["settings"])
    same([[NAME + "_observe", "INSERT", "AFTER"]], receipt["triggers"])
    same([[NAME, "InnoDB"], [NAME + "_events", "InnoDB"]], receipt["engines"])


def statement(tag, heading, rows):
    return {"dialect": "mysql", "release": tag, "target": {"schema": SCHEMA, "table": NAME,
            "columns": list(heading), "columnTypes": [TYPE[col] for col in heading]}, "rows": rows}


def cell(value):
    return text(value) if value is not None else None


def row(heading, key, **values):
    physical = {"row_key": str(key), **{name: cell(value) for name, value in (DEFAULT | values).items()}}
    return [physical[name] for name in heading]


def finite(tag, heading, count, value, tested):
    order = ORDER[heading]
    primary = row(order, 303, **{FIELDS[tested]: VALUES[value]})
    return statement(tag, order, [primary, row(order, 202)] if count == 2 else [primary])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = set(product(b["headings"], b["rowCounts"], b["textValues"], b["testedField"]))
    require(len(identities) == b["identities"], "mysql-varchar-insert-finite-size", len(identities))
    return identities


def unique_probe(observed):
    heading = observed["target"]["columns"]
    (key, *others) = [observed["rows"][0][heading.index(name)] for name in HEADING]
    require(key == "303", "mysql-varchar-insert-finite-row-key", key)
    changes = [(name, operand) for name, operand in zip(WIDTH, others) if operand != cell(DEFAULT[name])]
    require(len(changes) == 1, "mysql-varchar-insert-finite-distinct-field", changes)
    return changes[0]


def finite_id(_, observed, _item):
    name, operand = unique_probe(observed)
    by_value = {value: key for key, value in VALUES.items()}
    by_field = {value: key for key, value in FIELDS.items()}
    heading = {value: key for key, value in ORDER.items()}[tuple(observed["target"]["columns"])]
    return heading, len(observed["rows"]), by_value[None if operand is None else operand[1]], by_field[name]


def query(tag, sql, declaration=DECLARATION):
    return analysis_query(tag, sql, [declaration])


def analyzed(tag):
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES (303, 'x', 'R', '')",
           finite(tag, "natural", 1, "fitting", "nullable"))
    yield (f"INSERT INTO {TARGET} (required_value, empty_value, row_key, nullable_value) VALUE "
           "('é𐐷z', '', 303, 'N'), ('R', '', 202, 'N')", finite(tag, "reordered", 2, "wide", "required"))
    same_text = statement(tag, HEADING, [row(HEADING, 303), row(HEADING, 202)])
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES "
           "(303, 'N', 'R', ''), (202, 'N', 'R', '')", same_text)
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES (303, 'N', NULL, '')",
           finite(tag, "natural", 1, "null", "required"))
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES "
           "(303, 'N', NULL, ''), (202, 'N', 'R', '')", finite(tag, "natural", 2, "null", "required"))
    conflict = statement(tag, HEADING, [row(HEADING, 303), row(HEADING, 1)])
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES "
           "(303, 'N', 'R', ''), (1, 'N', 'R', '')", conflict)

    reordered = ("row_key", "required_value", "nullable_value", "empty_value")
    after_error = statement(tag, reordered, [row(reordered, 303, required_value=None, nullable_value="q'u", empty_value="a  ")])
    yield (f"INSERT INTO {TARGET} (row_key, required_value, nullable_value, empty_value) VALUES (303, NULL, 'q''u', 'a  ')", after_error)
    repeated_null = statement(tag, HEADING, [row(HEADING, 303, required_value=None, empty_value="a"),
                                             row(HEADING, 202, required_value=None, empty_value="b")])
    yield (f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES "
           "(303, 'N', NULL, 'a'), (202, 'N', NULL, 'b')", repeated_null)


def negative(tag):
    mismatch = "An INSERT value must match its native destination type."
    invalid = "InvalidConstruction"
    yield statement(tag, HEADING, [[text("signed"), text("Q"), text("R"), text("")]]), invalid, mismatch
    yield statement(tag, HEADING, [["303", "7", text("R"), text("")]]), invalid, mismatch
    yield statement(tag, HEADING, [["303", text("Q")]]), invalid, "Every inserted row must match the target width."
    repeated = ("row_key", "nullable_value", "nullable_value", "required_value")
    yield statement(tag, repeated, [["303", text("q"), text("R"), text("")]]), invalid, "A table source cannot repeat a column name."
    sql = f"INSERT INTO {TARGET} (row_key, nullable_value, required_value, empty_value) VALUES (303, 'q', 'R', '')"
    yield query(tag, sql, DECLARATION.replace(COLLATION, "utf8mb4_general_ci")), invalid, "The text slot requires an explicit utf8mb4_bin collation."
    generated = DECLARATION.replace("nullable_value VARCHAR(2) " + CAST,
                                    "nullable_value VARCHAR(2) " + CAST + " GENERATED ALWAYS AS (required_value)")
    # MySQL 5.6 rejects generated column declaration syntax. Later releases reject its explicit writable use.
    if tag == "mysql-5.6.51":
        yield query(tag, sql, generated), "AnalysisException", "Unexpected 'GENERATED' at line 1, column 146, expected )"
    else:
        yield (query(tag, sql, generated), "SemanticException",
               "The value specified for generated column 'nullable_value' in table 'ir_varchar_insert' is not allowed.")


def seeded(tag, seed, budget):
    rng = Random(seed)
    literal = ("", "a ", "a  ", "éz", "𐐷𐐀", "B\x00Q", "q'u", " 🇯🇵 ", "\t\n", None)
    for i in range(budget):
        order = list(HEADING)
        rng.shuffle(order)
        rows = []
        shared = {name: rng.choice(literal) for name in WIDTH}
        for j in range(i % 4 + 1):
            inputs = dict(shared) if i % 5 == 0 else {name: rng.choice(literal) for name in WIDTH}
            if i in (0, 1, 4):
                inputs["required_value"] = None
            key = 1 if i % 7 == 0 and j == 0 else 300 - j
            if i % 7 == 3 and j == 2:
                key = 300
            rows.append(row(order, key, **inputs))
        yield statement(tag, order, rows)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, key in enumerate(product(b["headings"], b["rowCounts"], b["textValues"], b["testedField"])):
        item = finite(tag, *key)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "mysql-varchar-insert-analysis-incomplete")
    for index, (sql, item) in enumerate(positives):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, exception, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + exception, "message": message}


def expected(item):
    baseline = {"rows": [list(row) for row in BASELINE], "events": []}
    after, events, warnings = [list(row) for row in BASELINE], [], []
    heading = item["target"]["columns"]
    outcome = {"status": "mutation", "count": len(item["rows"]), "columns": 0}
    occupied = {row[0] for row in after}
    release = item["release"]
    complete_failed_row = release in ("mysql-5.6.51", "mysql-5.7.44")
    null_warnings = set()
    for index, given in enumerate(item["rows"]):
        values = dict(zip(heading, given))
        key = int(values["row_key"])
        converted = {"row_key": key}
        for name in heading:
            if name == "row_key":
                continue
            operand = values[name]
            value = None if operand is None else operand[1]
            if value is None and name == "required_value":
                if len(item["rows"]) == 1:
                    outcome = {"status": "db-error", "state": "23000", "vendorCode": 1048}
                    warnings.append(["Error", 1048, name])
                    if not complete_failed_row:
                        break
                value = ""
                if outcome["status"] == "mutation" and (release == "mysql-5.6.51" or name not in null_warnings):
                    warnings.append(["Warning", 1048, name])
                    null_warnings.add(name)
            value, severity = truncated(value, WIDTH[name])
            if severity is not None:
                warnings.append([severity, 1265, name])
            converted[name] = value
        if outcome["status"] == "db-error":
            break
        if key in occupied:
            outcome = {"status": "db-error", "state": "23000", "vendorCode": 1062}
            warnings.append(["Error", 1062, str(key)])
            break
        occupied.add(key)
        stored = [converted[name] for name in HEADING]
        after.append(stored)
        events.append([index + 1, *stored])
    state = baseline if outcome["status"] == "db-error" else {"rows": sorted(after), "events": events}
    return {**outcome, "before": baseline, "restored": baseline, "after": state,
            "warningCount": len(warnings), "diagnostics": sorted(warnings)}


def observation(raw):
    return raw


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "mysql-varchar-insert-positive-rejected", response)
    same(item["target"], response["target"])
    same(item["rows"], response["rows"])


def check_observation(item, actual, sql):
    want = expected(item)
    witness = {"sql": sql, "expected": want, "observed": actual}
    require(want["status"] == actual["status"], "mysql-varchar-insert-native-result", witness)
    got = {**actual, "diagnostics": normalize_diagnostics(actual["diagnostics"], WIDTH)}
    require(want == got, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "mysql-varchar-insert-fault-native")


def sample_forms(forms, reply, *_):
    heading = reply["target"]["columns"]
    values = reply["rows"]
    forms[f"width/{len(values)}"] += 1
    for position in range(4):
        forms[f"column/{heading[position]}/{position}"] += 1
    keys = [row[heading.index("row_key")] for row in values]
    if len(set(keys)) != len(keys):
        forms["duplicate/source"] += 1
    if any(int(key) in {row[0] for row in BASELINE} for key in keys):
        forms["duplicate/target"] += 1
    for given in values:
        if given[heading.index("required_value")] is None:
            forms[f"required-null/{len(values) > 1}"] += 1
        for term in given:
            if isinstance(term, list) and "\x00" in term[1]:
                forms["text/nul"] += 1
            if isinstance(term, list) and term[1].endswith(" "):
                forms["text/padding"] += 1
    exact_text = [tuple(row[index] and tuple(row[index]) for index, name in enumerate(heading) if name != "row_key") for row in values]
    if len(set(exact_text)) != len(exact_text):
        forms["duplicate/text"] += 1


def required_forms(*_):
    return ({*(f"width/{size}" for size in range(1, 5)), *(f"column/{name}/{position}" for name, position in product(HEADING, range(4))),
             "duplicate/target", "duplicate/source", "duplicate/text", "required-null/True", "required-null/False",
             "text/nul", "text/padding"})


def controls():
    item = finite("mysql-9.1.0", "natural", 2, "fitting", "nullable")
    reference = expected(item)
    changed = {**reference, "after": {**reference["after"], "events": list(reversed(reference["after"]["events"]))}}
    check_observation(item, reference, "native insert order")
    try:
        check_observation(item, changed, "reverse event order")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-varchar-insert-wrong-control", error.detail)
    else:
        raise Failure("mysql-varchar-insert-comparator-accepted")
    return {"input": item, "expected": reference, "mismatch": changed}


def faults(_, tag):
    insert = "sql-semantics-mysql/src/Ir/Mutation/Insert.php"
    cells = "sql-semantics-mysql/src/Ir/Mutation/InsertCell.php"
    literal = "sql-semantics-mysql/src/Ir/Literal/TextLiteral.php"
    two = finite(tag, "natural", 2, "fitting", "nullable")
    heading = "implode(', ', $columns)"
    values = "implode(', ', $rows)"
    return [
        RenderFault("swap-native-varchar-insert-columns", insert, heading,
                    "implode(', ', [$columns[0], $columns[2], $columns[1], $columns[3]])", two, "meaning-mismatch"),
        RenderFault("drop-final-native-varchar-insert-row", insert, values,
                    "implode(', ', array_slice($rows, 0, -1))", two, "meaning-mismatch"),
        RenderFault("reverse-native-varchar-insert-rows", insert, values,
                    "implode(', ', array_reverse($rows))", two, "meaning-mismatch"),
        RenderFault("trim-native-varchar-insert-padding", literal, "strtr($this->value, [", "strtr(rtrim($this->value, ' '), [",
                    statement(tag, HEADING, [row(HEADING, 303, nullable_value="a ")]), "meaning-mismatch"),
        RenderFault("substitute-empty-text-for-insert-null", cells, "$value === null => 'NULL'",
                    "$value === null => \"''\"", finite(tag, "natural", 1, "null", "nullable"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
