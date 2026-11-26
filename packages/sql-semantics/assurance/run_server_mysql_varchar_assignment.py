"""Verify ordered MySQL VARCHAR assignments against an independent storage evaluator."""

from itertools import product
import json
from random import Random
import sys

from mysql_varchar_storage import normalize_diagnostics, truncated
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_effects import integer
from run_server_mysql_varchar_presence import logical, null_test
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, query as analysis_query
from server_join_cases import compare, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-assignment"
CONTRACT = "7bfd83feaa665f4619e27618f7402f43cd54c333714f37cc27bba2f4919276e2"
NAME = "ir_varchar_assignment"
TARGET = f"{SCHEMA}.{NAME}"
EVENTS = f"{SCHEMA}.{NAME}_events"
HEADING = ("row_key", "source_value", "nullable_value", "required_value", "empty_value", "id", "wide_source")
CAPACITY = {"source_value": 16, "nullable_value": 2, "required_value": 2, "empty_value": 0, "wide_source": 64}
DESTINATIONS = ("nullable_value", "required_value", "empty_value")
CONTENTS = (
    (1, "a ", "a", "A", "", 10, "a   "),
    (2, "é𐐷z", "a ", "é𐐷", "", 20, "é𐐷z"),
    (3, None, "", "", None, 30, "   "),
    (4, "a   ", "𐐷", "A", "", 40, None),
    (5, "quote's", None, "é", "", 50, "z " * 30 + "z  "),
    (6, "a\x00b", "b", "b", "", None, ""),
)
TYPES = {name: ["varchar", width, COLLATION, *([False] if name == "required_value" else [])]
         for name, width in CAPACITY.items()}
TYPES.update({"row_key": "int", "id": "int"})
DEFINITION = ", ".join(
    f"{name} INT{' NOT NULL PRIMARY KEY' if name == 'row_key' else ''}" if kind == "int"
    else f"{name} VARCHAR({kind[1]}) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"
         + (" NOT NULL" if name == "required_value" else "")
    for name, kind in TYPES.items()
)
DECLARATION = f"CREATE TABLE {TARGET} ({DEFINITION}) ENGINE=InnoDB"


def specifications():
    path, profile = TOOL / "mysql-varchar-assignment-v4.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-assignment-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeMysqlVarcharAssignment": True}


def fixture_literal(value):
    if value is None:
        return "NULL"
    if isinstance(value, int):
        return str(value)
    return "CONVERT(0x" + value.encode("utf-8").hex() + " USING utf8mb4)" if value else "''"


def prepare(_engine, db):
    names = ", ".join(HEADING)
    data = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in CONTENTS)
    events = ", ".join(f"{age}_{field_} VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"
                       if field_ != "id" else f"{age}_id INT"
                       for age, field_ in product(("old", "new"), ("nullable", "required", "empty", "id")))
    logged = ", ".join(f"{age}.{name}" for age, name in product(("OLD", "NEW"), (*DESTINATIONS, "id")))
    response = db.request({"fixture": [
        "SET SESSION sql_notes = 1, max_error_count = 64, lc_messages = 'en_US'",
        f"DROP TABLE IF EXISTS {EVENTS}", f"DROP TABLE IF EXISTS {TARGET}", DECLARATION,
        f"INSERT INTO {TARGET} ({names}) VALUES {data}",
        f"CREATE TABLE {EVENTS} (row_key INT, {events}) ENGINE=InnoDB",
        f"CREATE TRIGGER {TARGET}_observe AFTER UPDATE ON {TARGET} FOR EACH ROW "
        f"INSERT INTO {EVENTS} VALUES (OLD.row_key, {logged})",
    ]})
    forbidden = [f"UPDATE {SCHEMA}.wrong SET id = 1", f"UPDATE {TARGET} SET source_value = 'wrong'",
                 f"UPDATE {TARGET} SET nullable_value = 'a'; DELETE FROM {TARGET}"]
    outside = [db.request(observer_request(None, sql)) for sql in forbidden]
    before = db.request({"sql": f"SELECT {names} FROM {TARGET} ORDER BY row_key"})
    storage = db.request({"sql": "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES "
                                  f"WHERE TABLE_SCHEMA = '{SCHEMA}' AND TABLE_NAME IN ('{NAME}', '{NAME}_events') "
                                  "ORDER BY TABLE_NAME"})
    settings = db.request({"sql": "SELECT @@sql_notes, @@max_error_count, @@lc_messages"})
    triggers = db.request({"sql": "SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING FROM information_schema.TRIGGERS "
                                   f"WHERE TRIGGER_SCHEMA = '{SCHEMA}' AND EVENT_OBJECT_TABLE = '{NAME}'"})
    return {"fixture": response, "outside": outside, "rows": before, "storage": storage,
            "diagnosticSettings": settings["rows"], "triggers": triggers["rows"]}


def check_preparation(engine, _environment, observed):
    require(engine == "mysql", "mysql-varchar-assignment-engine", engine)
    same({"status": "fixture"}, observed["fixture"])
    same([[1, 64, "en_US"]], observed["diagnosticSettings"])
    same([[NAME + "_observe", "UPDATE", "AFTER"]], observed["triggers"])
    same([{"status": "outside-observer-scope"}] * 3, observed["outside"])
    same([[NAME, "InnoDB"], [NAME + "_events", "InnoDB"]], observed["storage"]["rows"])
    same([list(row) for row in CONTENTS], observed["rows"]["rows"])
    same(list(HEADING), [entry[0] for entry in observed["rows"]["fields"]])


def select_slot(name, occurrence=0):
    index = HEADING.index(name)
    return (slot if TYPES[name] == "int" else field)(index, occurrence)


def assignment(destination, operand):
    return [select_slot(destination), operand]


def value_for(role):
    return {
        "field": select_slot("source_value"), "wide-field": select_slot("wide_source"), "fitting": text("a"),
        "wide": text("é𐐷z"), "padding": text("a  "), "ascii-whitespace": text("\t\n"),
        "unicode-whitespace": text("\u00a0\u00a0\u00a0"), "null": ["null"],
    }[role]


def filtered(role):
    return None if role == "all" else null_test(select_slot("source_value"), True)


def statement(tag, assignments, where=None):
    return {"dialect": "mysql", "release": tag, "target": {"schema": SCHEMA, "table": NAME,
            "columns": list(HEADING), "columnTypes": [TYPES[name] for name in HEADING]}, "assignments": assignments, "where": where}


def finite(tag, destination, operand, predicate):
    return statement(tag, [assignment(destination, value_for(operand))], filtered(predicate))


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = set(product(b["destinations"], b["inputs"], b["predicates"]))
    require(len(identities) == b["identities"], "mysql-varchar-assignment-finite-size", len(identities))
    return identities


def address(term, target):
    name = target["columns"][term[1]]
    native = target["columnTypes"][term[1]]
    occurrence = term[2] if len(term) == 3 else 0
    require(occurrence == 0 and native == TYPES.get(name) and (term[0] == "slot") == (native == "int"),
            "mysql-varchar-assignment-address-kind", {"term": term, "target": target})
    return name


def predicate_shape(term, target):
    if term is None:
        return None
    role, *args = term
    if role in ("slot", "text-slot"):
        return role, address(term, target)
    if role in ("logic", "comparison"):
        return role, args[0], predicate_shape(args[1], target), predicate_shape(args[2], target)
    if role == "text-comparison":
        return role, args[0], args[1], predicate_shape(args[2], target), predicate_shape(args[3], target)
    if role == "null-test":
        return role, args[0], predicate_shape(args[1], target)
    require(role in ("null", "integer", "boolean", "text"), "mysql-varchar-assignment-operand-role", term)
    return tuple(term)


def graph(item):
    target = item["target"]
    return ((target["schema"], target["table"]), predicate_shape(item["where"], target),
            tuple((address(write, target), predicate_shape(value, target)) for write, value in item["assignments"]))


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "mysql-varchar-assignment-positive-rejected", response)
    same(graph(item), graph(response))


def finite_id(_, observed, _item):
    table, predicate, ((destination, operand),) = graph(observed)
    require(table == (SCHEMA, NAME) and predicate in (None, ("null-test", True, ("text-slot", "source_value"))),
            "mysql-varchar-assignment-finite-predicate", observed)
    roles = {("text-slot", "source_value"): "field", ("text-slot", "wide_source"): "wide-field", ("text", "a"): "fitting", ("text", "é𐐷z"): "wide",
             ("text", "a  "): "padding", ("text", "\t\n"): "ascii-whitespace",
             ("text", "\u00a0\u00a0\u00a0"): "unicode-whitespace", ("null",): "null"}
    return destination, roles[operand], "all" if predicate is None else "source-available"


def query(tag, sql, declarations=(DECLARATION,)):
    return analysis_query(tag, sql, list(declarations))


def analyzed(tag):
    yield f"UPDATE {TARGET} SET nullable_value = source_value", finite(tag, "nullable_value", "field", "all")
    yield (f"UPDATE {TARGET} SET nullable_value = NULL, required_value = NULL",
           statement(tag, [assignment(name, ["null"]) for name in DESTINATIONS[:2]]))
    qualified = [assignment("nullable_value", text("𐐷é")), assignment("required_value", select_slot("nullable_value"))]
    yield f"UPDATE {TARGET} AS r SET nullable_value = '𐐷é', required_value = r.nullable_value", statement(tag, qualified)
    repeated = [*qualified, assignment("nullable_value", text("z"))]
    yield f"UPDATE {TARGET} SET nullable_value = '𐐷é', required_value = nullable_value, nullable_value = 'z'", statement(tag, repeated)
    yield (f"UPDATE {TARGET} SET nullable_value = 'a ' WHERE source_value <=> 'a'",
           statement(tag, [assignment("nullable_value", text("a "))], comparison("<=>", select_slot("source_value"), text("a"))))

    assigning = assignment("required_value", ["null"])
    copying = assignment("nullable_value", select_slot("required_value"))
    yield (f"UPDATE {TARGET} SET required_value = NULL, nullable_value = required_value, required_value = 'y'",
           statement(tag, [assigning, copying, assignment("required_value", text("y"))]))
    yield (f"UPDATE {TARGET} SET required_value = NULL, nullable_value = required_value",
           statement(tag, [assigning, copying]))
    yield (f"UPDATE {TARGET} SET required_value = NULL, required_value = NULL",
           statement(tag, [assigning, assigning]))


def negative(tag):
    scope = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield statement(tag, [assignment("nullable_value", field(1, 1))]), "A VARCHAR UPDATE read slot must belong to occurrence zero."
    yield statement(tag, [[field(2, 1), text("a")]]), "A VARCHAR UPDATE target slot must belong to occurrence zero."
    yield statement(tag, [[field(5), text("a")]]), scope
    yield statement(tag, [assignment("nullable_value", field(5))]), scope
    yield query(tag, f"UPDATE {TARGET} SET nullable_value = 3"), "A VARCHAR UPDATE requires a text literal, NULL or a VARCHAR read slot."
    yield (query(tag, f"UPDATE {TARGET} SET nullable_value = source_value", [DECLARATION.replace(COLLATION, "utf8mb4_general_ci")]),
           "The text slot requires an explicit utf8mb4_bin collation.")


def seeded(tag, seed, budget):
    rng = Random(seed)
    reads = [select_slot(name) for name in CAPACITY]
    literals = [text(value) for value in ("", "a ", "a  ", "éz", "𐐷𐐀", "B\x00Q", "quote's", " 🇯🇵 ", "\t\n")]
    for index in range(budget):
        names = [rng.choice(DESTINATIONS) for _ in range(index % 5 + 1)]
        if index % 4 == 0:
            names[-1] = "id"
        nodes = [assignment(dest, rng.choice((select_slot("id"), integer(111), ["null"])) if dest == "id"
                            else rng.choice([*reads, *literals, ["null"]])) for dest in names]
        name = rng.choice(tuple(CAPACITY))
        predicate = (
            None, null_test(select_slot(name), bool(rng.getrandbits(1))),
            comparison(rng.choice(("<=>", "<", ">=", "=")), select_slot(name), rng.choice(literals)),
            logical("AND", compare(">", select_slot("row_key"), integer(rng.randint(0, 7))),
                    null_test(select_slot(name), bool(rng.getrandbits(1)))),
        )[index % 4]
        yield statement(tag, nodes, predicate)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, case in enumerate(product(b["destinations"], b["inputs"], b["predicates"])):
        item = finite(tag, *case)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "mysql-varchar-assignment-analysis-incomplete")
    for index, (sql, item) in enumerate(positives):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def observation(raw):
    return raw


def evaluated(term, current, target):
    if term[0] == "null":
        return None
    if term[0] == "text":
        return term[1]
    if term[0] == "integer":
        return int(term[1])
    return current[HEADING.index(address(term, target))]


def expected(item):
    target = item["target"]
    baseline = {"rows": [list(row) for row in CONTENTS], "events": []}
    after, events, diagnostics = [], [], []
    changed = 0
    deferred_required = item["release"] == "mysql-5.7.44"
    required_index = HEADING.index("required_value")
    for original in CONTENTS:
        current = list(original)
        if admits(item["where"], lambda term: evaluated(term, original, target)):
            for written, expr in item["assignments"]:
                dest = address(written, target)
                value = evaluated(expr, current, target)
                if value is None and dest == "required_value" and not deferred_required:
                    diagnostics.append(["Warning", 1048, dest])
                    value = ""
                if dest in CAPACITY and value is not None and len(value) > CAPACITY[dest]:
                    width = CAPACITY[dest]
                    fast_copy = expr[0] == "text-slot" and (CAPACITY[address(expr, target)] <= 63) == (width <= 63)
                    value, severity = truncated(value, width, fast_copy)
                    diagnostics.append([severity, 1265, dest])
                current[HEADING.index(dest)] = value
            if current[required_index] is None:
                diagnostics.append(["Warning", 1048, "required_value"])
                current[required_index] = ""
            events.append([current[0], *original[2:6], *current[2:6]])
            changed += int(tuple(current) != original)
        after.append(current)
    return {"status": "mutation", "count": changed, "columns": 0, "before": baseline, "restored": baseline,
            "after": {"rows": after, "events": events}, "warningCount": len(diagnostics), "diagnostics": sorted(diagnostics)}


def check_observation(item, actual, sql):
    wanted = expected(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "mutation", "mysql-varchar-assignment-native-rejection", witness)
    require(wanted == {**actual, "diagnostics": normalize_diagnostics(actual["diagnostics"], DESTINATIONS)}, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "mysql-varchar-assignment-fault-native")


def sample_forms(forms, reply, *_):
    _, predicate, assignments = graph(reply)
    forms[f"width/{len(assignments)}"] += 1
    forms[f"filter/{'all' if predicate is None else predicate[0]}"] += 1
    writes = set()
    for name, term in assignments:
        forms["target/" + name] += 1
        forms["value/" + term[0]] += 1
        if name in writes:
            forms["target/repeated"] += 1
        writes.add(name)
        if term[0] == "text-slot":
            forms["read/same" if name == term[1] else "read/other"] += 1
            forms["read/two-byte-prefix" if CAPACITY[term[1]] > 63 else "read/one-byte-prefix"] += 1
        if term[0] == "text":
            if any(ord(char) > 0xFFFF for char in term[1]):
                forms["text/supplementary"] += 1
            if "\x00" in term[1]:
                forms["text/nul"] += 1
            if term[1].endswith(" "):
                forms["text/padding"] += 1


def required_forms(*_):
    return ({*(f"width/{size}" for size in range(1, 6)), *(f"target/{name}" for name in (*DESTINATIONS, "id", "repeated")),
             "filter/all", "filter/null-test", "filter/text-comparison", "filter/logic",
             "value/text-slot", "value/text", "value/null", "value/integer", "value/slot",
             "read/same", "read/other", "read/two-byte-prefix", "read/one-byte-prefix", "text/supplementary", "text/nul", "text/padding"})


def controls():
    item = finite("mysql-9.1.0", "nullable_value", "wide", "all")
    reference = expected(item)
    actual = {**reference, "diagnostics": [[level, code, f"Data truncated for column '{name}' at row {index + 1}"]
                                           for index, (level, code, name) in enumerate(reference["diagnostics"])]}
    check_observation(item, actual, "wide fixture text")
    mismatch = {**actual, "warningCount": actual["warningCount"] - 1}
    try:
        check_observation(item, mismatch, "drop one observed warning")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-varchar-assignment-wrong-control", error.detail)
    else:
        raise Failure("mysql-varchar-assignment-comparator-accepted")
    return {"input": item, "expected": reference, "positive": actual, "mismatch": mismatch}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/Mutation/Update.php"
    literal = "sql-semantics-mysql/src/Ir/Literal/TextLiteral.php"
    order = [assignment("nullable_value", text("𐐷a")), assignment("required_value", select_slot("nullable_value"))]
    return [
        RenderFault("reverse-varchar-assignment-order", renderer, "foreach ($this->assignments as $assignment)",
                    "foreach (array_reverse($this->assignments) as $assignment)", statement(tag, order), "meaning-mismatch"),
        RenderFault("read-the-wrong-varchar-write-operand", renderer, "$sql->expression($assignment->value, $this->target)",
                    "$sql->expression(new \\SqlSemantics\\Platform\\MySql\\Ir\\From\\TextColumnReference(3), $this->target)",
                    finite(tag, "nullable_value", "field", "all"), "meaning-mismatch"),
        RenderFault("trim-varchar-assignment-padding", literal, "strtr($this->value, [", "strtr(rtrim($this->value, ' '), [",
                    statement(tag, [assignment("nullable_value", text("a "))]), "meaning-mismatch"),
        RenderFault("drop-varchar-assignment-filter", renderer, "if ($this->where !== null)", "if (false)",
                    finite(tag, "nullable_value", "fitting", "source-available"), "meaning-mismatch"),
        RenderFault("drop-final-varchar-assignment", renderer, "implode(', ', $parts)", "implode(', ', array_slice($parts, 0, -1))",
                    statement(tag, order), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
