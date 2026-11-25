"""Compare MySQL's physical VARCHAR slots, metadata and PAD SPACE results."""

from collections import Counter, defaultdict
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_value as check_select, main, observer_request
from server_grouped_cases import aggregate, case as grouped
from server_join_cases import case, compare, join, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-slots"
CONTRACT = "3282a89029e03bbab4448271d03cfc292eb18947d3ff0295b245290bea0eb559"
DIAGNOSTICS = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\"
SCHEMA, PRIMARY, SECONDARY = "ir_fixture", "ir_varchar_parent", "ir_varchar_secondary"
HEADING = ("label", "id", "alternate", "empty_value")
LENGTHS = {"label": 32, "alternate": 8, "empty_value": 0}
COLLATION = "utf8mb4_bin"
TYPES = {name: ["varchar", length, COLLATION] for name, length in LENGTHS.items()} | {"id": "int"}
DATA = {
    PRIMARY: [("z", 1, "blue", ""), ("A", 2, "green", ""), ("a", 2, "green", ""),
              ("a ", 2, "green", ""), ("a", 2, "green", ""), ("a\x00", 3, "1", ""), ("", 4, None, ""),
              ("é", 5, "é", ""), ("é", 6, "é", ""), ("𐐷", 7, "δ", None), ("𐐀", 7, "α", ""),
              (None, 8, "was-null", None), ("space ", None, "", "")],
    SECONDARY: [("destination", 2, "wrong", ""), ("destination", 2, "wrong", ""), ("joined ", 6, "right", ""),
                (None, 7, "null", ""), ("orphan", 80, "only", None)],
}
DECLARATIONS = [
    f"CREATE TABLE {SCHEMA}.{name} (label VARCHAR(32) CHARACTER SET utf8mb4 COLLATE {COLLATION}, id INTEGER, "
    f"alternate VARCHAR(8) CHARACTER SET utf8mb4 COLLATE {COLLATION}, empty_value VARCHAR(0) COLLATE {COLLATION})"
    for name in DATA
]


def specifications():
    path, profile = TOOL / "mysql-varchar-slots-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-slots-contract-mismatch")
    specification = json.loads(path.read_text())
    previous = specification["previous"]
    require(digest(TOOL / previous["path"]) == previous["sha256"], "mysql-varchar-slots-previous-contract-mismatch")
    return specification, {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(_, db):
    def atom(value):
        if value is None:
            return "NULL"
        if isinstance(value, int):
            return str(value)
        return f"CONVERT(0x{value.encode().hex()} USING utf8mb4)" if value else "''"

    statements = [*(f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in DATA), *DECLARATIONS]
    for name, rows in DATA.items():
        values = ", ".join("(" + ", ".join(map(atom, row)) + ")" for row in rows)
        statements.append(f"INSERT INTO {SCHEMA}.{name} VALUES {values}")
    setup = db.request({"fixture": statements})
    facts = db.request({"sql": "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME "
                               f"FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{SCHEMA}' "
                               f"AND TABLE_NAME IN ('{PRIMARY}', '{SECONDARY}') AND DATA_TYPE = 'varchar' "
                               "ORDER BY TABLE_NAME, ORDINAL_POSITION"})
    native = db.request({"sql": f"SELECT CHARSET(label) AS charset_name, COLLATION(label) AS collation_name, COERCIBILITY(label) AS strength "
                                f"FROM {SCHEMA}.{PRIMARY} LIMIT 1"})
    pad = db.request({"sql": f"SELECT _utf8mb4 0x61 COLLATE {COLLATION} = _utf8mb4 0x6120 AS padded_equal, "
                             f"_utf8mb4 0x61 COLLATE {COLLATION} > _utf8mb4 0x6100 AS nul_before_padding"})
    return {"fixture": setup, "definitions": facts, "coercibility": native, "pad": pad}


def check_preparation(_, _environment, prepared):
    same({"status": "fixture"}, prepared["fixture"])
    for name in ("definitions", "coercibility", "pad"):
        require(prepared[name]["status"] == "rows", "mysql-varchar-slots-setup", prepared)
    same([[name, column, size, "utf8mb4", COLLATION] for name in sorted(DATA) for column, size in LENGTHS.items()],
         prepared["definitions"]["rows"])
    same([["utf8mb4", COLLATION, 2]], prepared["coercibility"]["rows"])
    same([[1, 1]], prepared["pad"]["rows"])


def table(name=PRIMARY, columns=HEADING):
    kinds = [TYPES[column] for column in columns]
    return {"schema": SCHEMA, "table": name, "columns": list(columns),
            **({"columnTypes": kinds} if any(kind != "int" for kind in kinds) else {})}


def field(position, occurrence=0):
    return ["text-slot", position, occurrence]


def project(tag, source, columns, distinct=False, order=()):
    return {**case("mysql", tag, source, columns), "distinct": distinct, "orderBy": list(order), "limit": None}


def finite(tag, layout, quantifier, ordering):
    parent = table()
    source = join("LEFT", parent, table(SECONDARY), compare("=", slot(1), slot(1, 1))) if layout == "join" else parent
    columns = {
        "only-text": [[field(0), "value"]], "mixed": [[field(0), "value"], [slot(1), "id"]],
        "repeat": [[field(0), "value"], [field(0), "value"]], "join": [[field(0), "value"], [field(0, 1), "later"]],
    }[layout]
    order = () if ordering == "bag" else [[1, ordering == "descending", ordering == "ascending"]]
    return project(tag, source, columns, quantifier == "distinct", order)


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    expected = set(product(b["layouts"], b["quantifiers"], b["ordering"]))
    require(len(expected) == b["identities"], "mysql-varchar-slots-finite-size", len(expected))
    return expected


def finite_id(_, actual, _item):
    cols, source, order = actual["columns"], actual["source"], actual["orderBy"]
    expected_type = TYPES["label"]
    require(cols[0][0] == field(0) and
            (source["left"] if "kind" in source else source)["columnTypes"][0] == expected_type,
            "mysql-varchar-slots-missing-physical-type", actual)
    if "kind" in source:
        require(cols[1][0] == field(0, 1) and source["right"]["columnTypes"][0] == expected_type,
                "mysql-varchar-slots-missing-right-physical-type", actual)
        layout = "join"
    elif len(cols) == 1:
        layout = "only-text"
    else:
        layout = {("text-slot", True): "repeat", ("slot", False): "mixed"}[(cols[1][0][0], cols[0][1] == cols[1][1])]
    return layout, "distinct" if actual["distinct"] else "all", "bag" if not order else "descending" if order[0][1] else "ascending"


def query(tag, sql, declarations=DECLARATIONS):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": declarations}


def analyzed(tag):
    left, right = f"{SCHEMA}.{PRIMARY}", f"{SCHEMA}.{SECONDARY}"
    yield (f"SELECT label AS `first_value`, t.label AS `second_value` FROM {left} t",
           project(tag, table(columns=("label",)), [[field(0), "first_value"], [field(0), "second_value"]]))
    yield (f"SELECT * FROM {left}", project(tag, table(), [[slot(i) if name == "id" else field(i), name]
                                                         for i, name in enumerate(HEADING)]))
    yield (f"SELECT alternate, label FROM {left}", project(tag, table(columns=("alternate", "label")),
                                                         [[field(0), "alternate"], [field(1), "label"]]))
    scope = join("LEFT", table(columns=("label", "id")), table(SECONDARY, ("label", "id")), compare("=", slot(1), slot(1, 1)))
    yield (f"SELECT a.label AS left_text, b.label AS right_text FROM {left} a LEFT JOIN {right} b ON a.id = b.id",
           project(tag, scope, [[field(0), "left_text"], [field(0, 1), "right_text"]]))
    yield (f"SELECT empty_value FROM {left}", project(tag, table(columns=("empty_value",)), [[field(0), "empty_value"]]))


def negative(tag):
    source = table()
    varchar_slot = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield project(tag, source, [[field(1), "bad"]]), varchar_slot
    yield project(tag, source, [[slot(0), "bad"]]), "Column slot must belong to the SELECT source."
    yield project(tag, source, [[field(0, 1), "bad"]]), varchar_slot
    yield project(tag, source, [[field(7), "bad"]]), varchar_slot
    yield project(tag, {**source, "columnTypes": [TYPES["label"]]}, [[field(0), "bad"]]), "A table source needs one native type per input slot."
    target = table(columns=("label",))
    for length in (-1, 16384):
        unrepresentable = {**target, "columnTypes": [["varchar", length, COLLATION]]}
        yield project(tag, unrepresentable, [[field(0), "bad"]]), "A utf8mb4 VARCHAR length must be between 0 and 16383 characters."
    yield {"dialect": "mysql", "release": tag, "target": target, "rows": [["1"]]}, "An INSERT value must match its native destination type."
    yield grouped("mysql", tag, target, [], [[aggregate("sum", slot(0)), "bad"]]), "The aggregate slot must belong to the group input."
    sql = f"SELECT label FROM {SCHEMA}.{PRIMARY}"
    yield query(tag, sql, [f"CREATE TABLE {SCHEMA}.{PRIMARY} (label VARCHAR(32) CHARACTER SET utf8mb4)"]), "The text slot requires an explicit utf8mb4_bin collation."
    yield query(tag, sql, [f"CREATE TABLE {SCHEMA}.{PRIMARY} (label VARCHAR(32) COLLATE utf8mb4_general_ci)"]), "The text slot requires an explicit utf8mb4_bin collation."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        kinds = (None, "LEFT", "RIGHT", "INNER")
        kind = kinds[index % len(kinds)]
        outer = list(HEADING)
        later = list(HEADING)
        rng.shuffle(outer)
        rng.shuffle(later)
        left, right = table(columns=outer), table(SECONDARY, later)
        output = []
        for position in range(1 + rng.randrange(5)):
            occurrence = rng.randrange(2) if kind else 0
            heading = later if occurrence else outer
            atom = rng.choice(heading)
            term = slot(heading.index(atom), occurrence) if atom == "id" else field(heading.index(atom), occurrence)
            output.append([term, rng.choice(("same", "値", f"r{position}"))])
        source = left if kind is None else join(kind, left, right, compare("=", slot(outer.index("id")), slot(later.index("id"), 1)))
        descending = bool(rng.getrandbits(1))
        order = [[1, descending, not descending]] if index % 2 else []
        yield project(tag, source, output, bool(rng.getrandbits(1)), order)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, point in enumerate(product(b["layouts"], b["quantifiers"], b["ordering"])):
        item = finite(tag, *point)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "mysql-varchar-slots-analysis-incomplete")
    for index, (sql, item) in enumerate(positives):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, description) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": description}


def observation(raw):
    return raw


def row_stream(layout):
    if "kind" not in layout:
        columns = [HEADING.index(name) for name in layout["columns"]]
        return [(tuple(row[i] for i in columns),) for row in DATA[layout["table"]]]
    left, right = row_stream(layout["left"]), row_stream(layout["right"])
    observed = [(i, j, a + b) for i, a in enumerate(left) for j, b in enumerate(right)
                if admits(layout["on"], lambda term: value(term, a + b))]
    rows = [row for _, _, row in observed]
    if layout["kind"] == "LEFT":
        selected = {i for i, _, _ in observed}
        rows.extend(row + ((None,) * len(layout["right"]["columns"]),) for i, row in enumerate(left) if i not in selected)
    elif layout["kind"] == "RIGHT":
        selected = {j for _, j, _ in observed}
        rows.extend(((None,) * len(layout["left"]["columns"]),) + row for j, row in enumerate(right) if j not in selected)
    else:
        require(layout["kind"] == "INNER", "mysql-varchar-slots-unsupported-join-role", layout["kind"])
    return rows


def value(role, row):
    if role[0] == "text":
        return role[1]
    if role[0] == "null":
        return None
    require(role[0] in ("slot", "text-slot"), "mysql-varchar-slots-oracle-operand", role)
    _, position, *occurrence = role
    return row[occurrence[0] if occurrence else 0][position]


def grouped_value(value):
    return value.rstrip(" ") if isinstance(value, str) else value


def order_key(value):
    if isinstance(value, str):
        # PAD SPACE compares a short value with virtual U+0020 through its peer's end.
        return value.ljust(32, " ").encode("utf-8")
    return value


def expected(item):
    fields = [[alias, "VAR_STRING" if role[0] == "text-slot" else "LONG"] for role, alias in item["columns"]]
    return {"status": "rows", "fields": fields,
            "rows": [[value(role, row) for role, _ in item["columns"]] for row in row_stream(item["source"])],
            "distinct": item["distinct"], "orderBy": item["orderBy"]}


def check_observation(item, actual, sql):
    wanted = expected(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "mysql-varchar-slots-native-rejection", witness)
    require(actual["fields"] == wanted["fields"], "meaning-mismatch", witness)
    actual_rows = [tuple(row) for row in actual["rows"]]
    oracle = [tuple(row) for row in wanted["rows"]]
    if item["distinct"]:
        classes = defaultdict(set)
        for row in oracle:
            classes[tuple(map(grouped_value, row))].add(row)
        found = {tuple(map(grouped_value, row)): row for row in actual_rows}
        same_policy = len(found) == len(actual_rows) and found.keys() == classes.keys() and all(row in classes[key] for key, row in found.items())
    else:
        same_policy = Counter(actual_rows) == Counter(oracle)
    require(same_policy, "meaning-mismatch", witness)
    if item["orderBy"]:
        [(position, descending, nulls_first)] = item["orderBy"]
        keys = [order_key(row[position - 1]) for row in actual_rows]
        nonnull = sorted((key for key in keys if key is not None), reverse=descending)
        nulls = [None] * keys.count(None)
        require(keys == (nulls + nonnull if nulls_first else nonnull + nulls), "meaning-mismatch", witness)


def check_value(item, response):
    check_select(item, response)
    require(all(item[key] == response[key] for key in ("orderBy", "limit", "distinct")), "mysql-varchar-slots-result-shape", response)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-varchar-slots-fault-native")


def sample_forms(forms, value, *_):
    for exp, _ in value["columns"]:
        forms["role/" + exp[0]] += 1
        forms["occurrence/" + str(exp[2] if len(exp) > 2 else 0)] += 1
    forms[f"distinct/{value['distinct']}"] += 1
    forms["join/" + value["source"].get("kind", "single")] += 1
    names = [alias for _, alias in value["columns"]]
    if len(names) != len(set(names)):
        forms["names/repeated"] += 1
    source = value["source"]
    tables = (source["left"], source["right"]) if "kind" in source else (source,)
    if any(table_["columns"] != list(HEADING) for table_ in tables):
        forms["heading/reordered"] += 1


def required_forms(*_):
    return {"role/text-slot", "role/slot", "occurrence/0", "occurrence/1", "distinct/True", "distinct/False",
            "join/LEFT", "join/RIGHT", "join/INNER", "join/single", "names/repeated", "heading/reordered"}


def controls():
    item = finite("mysql-9.1.0", "only-text", "distinct", "bag")
    prediction = expected(item)
    values = list({grouped_value(row[0]): row for row in prediction["rows"]}.values())
    correct = {"status": "rows", "fields": prediction["fields"], "rows": values}
    check_observation(item, correct, "one actual row for each padded comparison class")
    wrong = {**correct, "rows": [["invented"] if row[0] == "z" else row for row in values]}
    require(wrong != correct, "mysql-varchar-slots-empty-control")
    try:
        check_observation(item, wrong, "replace one selected representative")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "mysql-varchar-slots-wrong-control", failure.detail)
    else:
        raise Failure("mysql-varchar-slots-comparator-accepted")
    return {"input": item, "expected": prediction, "knownMismatch": wrong}


def faults(_, tag):
    source = "sql-semantics-mysql/src/Ir/SelectSql.php"
    pattern = "$input->columns[$expression->slot]->quoted()"
    return [
        RenderFault("read-wrong-varchar-slot", source, pattern,
                    "$input->columns[$expression instanceof TextColumnReference ? 2 : $expression->slot]->quoted()",
                    finite(tag, "only-text", "all", "bag"), "meaning-mismatch"),
        RenderFault("read-first-varchar-occurrence", source, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", finite(tag, "join", "all", "bag"), "meaning-mismatch"),
        RenderFault("remove-varchar-distinct", source, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '", "'SELECT '",
                    finite(tag, "only-text", "distinct", "ascending"), "meaning-mismatch"),
        RenderFault("rename-repeated-varchar-output", source, "$name->quoted();",
                    "($slot === 1 ? '`different`' : $name->quoted());",
                    finite(tag, "repeat", "all", "bag"), "meaning-mismatch"),
        RenderFault("use-varchar-foreign-collation", source, pattern,
                    pattern + " . ($expression instanceof TextColumnReference ? ' COLLATE utf8mb4_general_ci' : '')",
                    finite(tag, "only-text", "distinct", "ascending"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
