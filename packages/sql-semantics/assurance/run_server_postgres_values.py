"""Compare directly constructed and analyzed PostgreSQL VALUES with native typed row bags."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as profile_contract
from run_server_join import main, probe
from server_cases import observation as unordered
from sqlite_domain import RenderFault

LABEL = "postgres-values"
OBSERVER = "server_observer.php"
CONTRACT_NAME = "postgres-values-v3.json"
CONTRACT = "602fa84cd6517e5e56f7b4967669399194522ff952e36183d3acf17c08c9c3c8"
COMPLETE_SEEDS = True
AXES = ("rows", "width", "kind", "layout")
KINDS = ("integer", "bigint", "boolean", "text")
CELL_TAG = {"integer": "integer", "bigint": "wide-integer", "boolean": "boolean", "text": "text"}
TYPES = {"integer": ["int4", 23], "bigint": ["int8", 20], "boolean": ["bool", 16], "text": ["text", 25]}
BY_TAG = {tag: kind for kind, tag in CELL_TAG.items()}
BY_NATIVE = {name: kind for kind, (name, _) in TYPES.items()}
REPEATED = {"integer": "31", "bigint": "71", "boolean": False, "text": "mirror"}
LITERALS = {
    "integer": ["-2147483648", "-7", "0", "5", "2147483647"],
    "bigint": ["-9223372036854775808", "-31", "0", "4294967296", "9223372036854775807"],
    "boolean": [False, True],
    "text": ["", "it's", "back\\slash", "猫", "🦉", "  spaced  "],
}
DIAGNOSTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-values-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": profile_contract()[1]["postgres"]}


def cell(kind, value):
    return [CELL_TAG[kind], value]


def typed_null(kind):
    return ["builtin-cast", TYPES[kind][0], ["null"]]


def case(tag, rows):
    return {"dialect": "postgres", "release": tag, "postgresValues": rows}


def kind_at(kind, position):
    return KINDS[(KINDS.index(kind) + position) % len(KINDS)]


def finite(tag, rows, width, kind, layout):
    output = []
    for row in range(rows):
        result = []
        for position in range(width):
            native = kind_at(kind, position)
            if layout == "repeat":
                result.append(cell(native, REPEATED[native]))
            elif layout == "null-first" and row == 0 and position == 0:
                result.append(typed_null(native) if rows == 1 else ["null"])
            else:
                values = LITERALS[native]
                index = row + position + int(native == "boolean")
                result.append(cell(native, values[index % len(values)]))
        output.append(result)
    return case(tag, output)


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "postgres-values-finite-size")
    return grid


def finite_id(_, actual, _item):
    rows = actual["postgresValues"]
    kind = BY_NATIVE[actual["columnKinds"][0]]
    first = rows[0][0]
    if first[0] in ("null", "builtin-cast"):
        layout = "null-first"
    elif first[1] == REPEATED[kind] and all(row == rows[0] for row in rows):
        layout = "repeat"
    else:
        layout = "typed"
    return len(rows), len(rows[0]), kind, layout


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": []}


def analyzed(tag):
    null = ["null"]
    yield "VALUES (-2147483648), (9223372036854775807)", case(tag, [[cell("integer", "-2147483648")], [cell("bigint", "9223372036854775807")]])
    yield "VALUES (TRUE, '猫'), (TRUE, '猫'), (FALSE, NULL)", case(tag, [[cell("boolean", True), cell("text", "猫")], [cell("boolean", True), cell("text", "猫")], [cell("boolean", False), null]])
    yield "VALUES (NULL), (NULL), (5)", case(tag, [[null], [null], [cell("integer", "5")]])
    yield "VALUES (NULL), (NULL)", case(tag, [[null], [null]])
    yield "VALUES (CAST(NULL AS boolean), CAST(NULL AS bigint))", case(tag, [[typed_null("boolean"), typed_null("bigint")]])
    yield "VALUES ('é'), (CAST('it''s' AS pg_catalog.text))", case(tag, [[cell("text", "é")], [cell("text", "it's")]])
    yield "VALUES (NULL, 'same'), (NULL, 'same'), (7, 'other')", case(tag, [[null, cell("text", "same")], [null, cell("text", "same")], [cell("integer", "7"), cell("text", "other")]])
    yield "VALUES (4), (4294967296), (NULL)", case(tag, [[cell("integer", "4")], [cell("bigint", "4294967296")], [null]])


def negatives(tag):
    yield case(tag, []), "VALUES needs a nonempty list of rows."
    yield case(tag, [[]]), "VALUES requires a nonempty ordered list of cells."
    yield case(tag, [[["null"]], [["null"], ["null"]]]), "VALUES rows must have equal widths."
    yield case(tag, [[cell("boolean", True)], [cell("integer", "1")]]), "VALUES column types are incompatible."
    yield case(tag, [[cell("text", "true")], [cell("boolean", True)]]), "VALUES column types are incompatible."
    yield case(tag, [[["builtin-cast", "int8", cell("integer", "1")]]]), "A VALUES cast cell must cast literal NULL."
    yield request(tag, "VALUES (DEFAULT)"), "The operand has no complete PostgreSQL projection representation."
    yield case(tag, [[["null"]] * 1665]), "A PostgreSQL VALUES result can have at most 1664 columns."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        height, width = 1 + rng.randrange(5), 1 + rng.randrange(4)
        kinds = [rng.choice(KINDS) for _ in range(width)]
        rows = []
        for _ in range(height):
            row = []
            for kind in kinds:
                decision = rng.randrange(5)
                if decision == 0:
                    row.append(["null"])
                elif decision == 1:
                    row.append(typed_null(kind))
                else:
                    effective = "integer" if kind == "bigint" and decision == 2 else kind
                    row.append(cell(effective, rng.choice(LITERALS[effective])))
            rows.append(row)
        if index % 5 == 0 and height > 1:
            rows[-1] = rows[0]
        yield case(tag, rows)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "postgres-values-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql), item, None
    for index, edge in enumerate(spec["positiveBoundaries"]):
        item = case(tag, [[cell(edge["fieldKind"], str(position)) for position in range(edge["width"])] for _ in range(edge["rows"])])
        yield f"boundary/{index}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    refused = list(negatives(tag))
    require(len(refused) == len(spec["negative"]), "postgres-values-negative-count")
    for index, (item, message) in enumerate(refused):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def native_kinds(item):
    selected = []
    rows = item["postgresValues"]
    require(rows and all(len(row) == len(rows[0]) for row in rows), "postgres-values-oracle-width")
    for column in zip(*rows):
        kinds = {BY_NATIVE[value[1]] if value[0] == "builtin-cast" else BY_TAG[value[0]]
                 for value in column if value[0] != "null"}
        if not kinds:
            chosen = "text"
        elif kinds <= {"integer", "bigint"}:
            chosen = "bigint" if "bigint" in kinds else "integer"
        else:
            require(len(kinds) == 1, "postgres-values-oracle-domain", sorted(kinds))
            chosen, = kinds
        selected.append(chosen)
    return selected


def predict(item):
    def value(cell):
        if cell[0] in ("null", "builtin-cast"):
            return None
        if cell[0] in ("integer", "wide-integer"):
            return int(cell[1])
        return cell[1]

    return {"status": "rows",
            "fields": [[f"column{index + 1}", *TYPES[kind]] for index, kind in enumerate(native_kinds(item))],
            "rows": [[value(cell) for cell in row] for row in item["postgresValues"]]}


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-values-positive-rejected", response)
    same(item["postgresValues"], response["postgresValues"])
    same([TYPES[kind][0] for kind in native_kinds(item)], response["columnKinds"])


def observation(raw):
    return unordered(raw)


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def prepare(_, _db):
    return {}


def check_preparation(_, _environment, preparation):
    same({}, preparation)


def check_observation(item, actual, sql):
    expected = observation(predict(item))
    detail = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "rows", "postgres-values-native-rejection", detail)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, reply, _, _item):
    rows = reply["postgresValues"]
    forms[f"height/{len(rows)}"] += 1
    forms[f"width/{len(rows[0])}"] += 1
    forms[f"repeat/{len(set(map(json.dumps, rows))) < len(rows)}"] += 1
    for row in rows:
        for term in row:
            forms[f"cell/{term[0]}"] += 1
            if term == ["integer", "-2147483648"] or term == ["integer", "2147483647"]:
                forms["boundary/int4"] += 1
            if term == ["wide-integer", "-9223372036854775808"] or term == ["wide-integer", "9223372036854775807"]:
                forms["boundary/int8"] += 1


def required_forms(*_):
    return {*(f"cell/{kind}" for kind in ("integer", "wide-integer", "boolean", "text", "null", "builtin-cast")),
            "repeat/True", "repeat/False", "height/1", "height/5", "width/1", "width/4", "boundary/int4", "boundary/int8"}


def controls():
    item = finite("pg-17.2", 3, 2, "bigint", "repeat")
    healthy = observation(predict(item))
    check_observation(item, healthy, "control")
    broken = {"lost-duplicate": {**healthy, "rows": healthy["rows"][:-1]},
              "wrong-native-kind": {**healthy, "fields": [["column1", "int4", 23], healthy["fields"][1]]}}
    results = {}
    for name, actual in broken.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            results[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-values-undetected-control", name)
    return results


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-values-mutant-response", reply)
    raise Failure("postgres-values-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(observation(predict(rule.item)), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-values-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Rows/Values"
    invalid, message = list(negatives(tag))[2]
    return [
        RenderFault("values-drop-row", f"{root}/ValuesQuery.php",
                    "foreach ($this->rows as $row)", "foreach (array_slice($this->rows, 0, 1) as $row)",
                    finite(tag, 3, 2, "integer", "repeat"), "meaning-mismatch"),
        RenderFault("values-switch-field-positions", f"{root}/ValuesRow.php",
                    "foreach ($this->cells as $cell)", "foreach (array_reverse($this->cells) as $cell)",
                    finite(tag, 2, 2, "text", "typed"), "meaning-mismatch"),
        RenderFault("values-lose-bigint-width", f"{root}/ValuesCell.php",
                    "return (new SelectSql())->expression($this->value, null);",
                    "return str_replace('pg_catalog.int8', 'pg_catalog.int4', (new SelectSql())->expression($this->value, null));",
                    case(tag, [[cell("bigint", "4")]]), "meaning-mismatch"),
        RenderFault("values-lose-typed-null", f"{root}/ValuesCell.php",
                    "return (new SelectSql())->expression($this->value, null);",
                    "return $this->value instanceof BuiltinCast ? 'NULL' : (new SelectSql())->expression($this->value, null);",
                    case(tag, [[typed_null("bigint")], [cell("integer", "1")]]), "meaning-mismatch"),
        RenderFault("values-allow-ragged-rows", f"{root}/ValuesQuery.php",
                    "count($row->cells) === $width,", "true,",
                    {"boundary": {"exception": DIAGNOSTIC, "message": message}}, "postgres-values-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
