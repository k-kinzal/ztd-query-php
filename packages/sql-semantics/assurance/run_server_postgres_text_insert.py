"""Compare mixed PostgreSQL VALUES layouts and exact native text values."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from run_server_postgres_text_returning import (
    COLUMN_TYPES, DEFAULTS, HEADING, INITIAL, OBSERVER, TABLE, address, analysis_request,
    check_preparation, graph, mutation, observation, prepare, reference,
)
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_join_cases import slot
from sqlite_domain import RenderFault

LABEL = "postgres-text-insert"
CONTRACT = "3fdc3cb62f73a95c63470c1713d6636f5e30b348ecaa370233a44646482539d5"
LAYOUTS = {
    "text": ("label",), "integer-text": ("id", "label"), "text-integer": ("label", "id"),
    "two-text": ("note", "label"),
}
TEXT = ("", "text 'signed'", "line\nnext", "朝 α", "back\\slash", " ")


def specifications():
    path, profile = TOOL / "postgres-text-insert-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-insert-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def insert(tag, columns, values, returned=(("id", "id"), ("label", "label"), ("note", "note"))):
    item = mutation(tag, "insert", returned)
    item["written"] = [reference(name) for name in columns]
    item["rows"] = values
    return item


def finite(tag, order, payload, multiplicity):
    columns = LAYOUTS[order]
    value = payload.removeprefix("int4:") if payload.startswith("int4:") else (
        {"empty": ["text", ""], "nonempty": ["text", "value 'α"], "null": None}[payload])
    row = [value] if "id" not in columns else (["13", value] if columns[0] == "id" else [value, "13"])
    if order == "two-text":
        row += [["text", "other field"]]
    return insert(tag, columns, [row] if multiplicity == "single" else [row, row[:]])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coordinates = set(product(b["layout"], b["payload"], b["rows"]))
    require(len(coordinates) == b["identities"], "postgres-text-insert-finite-size", coordinates)
    return coordinates


def finite_id(_, response, _item):
    _, _, _, operation, (rows, columns), _ = graph(response)
    require(operation == "insert", "postgres-text-insert-finite-role", operation)
    name = {layout: key for key, layout in LAYOUTS.items()}[tuple(columns)]
    index = next(i for i, column in enumerate(columns) if column != "id")
    cell = rows[0][index]
    payload = "null" if cell is None else f"int4:{cell}" if isinstance(cell, str) else (
        "empty" if cell == ["text", ""] else "nonempty")
    return name, payload, "single" if len(rows) == 1 else "repeated"


def analyzed(tag):
    table = f"public.{TABLE}"
    tail = "RETURNING id, label, note"
    yield f"INSERT INTO {table} (label) VALUES ('added') {tail}", insert(tag, ("label",), [[["text", "added"]]])
    yield (f"INSERT INTO {table} (label, id) VALUES ('same', 3), ('same', 3) {tail}",
           insert(tag, ("label", "id"), [[["text", "same"], "3"], [["text", "same"], "3"]]))
    yield (f"INSERT INTO {table} (note, label) VALUES (CAST('朝' AS pg_catalog.text), NULL) "
           "RETURNING note AS sample, label AS other",
           insert(tag, ("note", "label"), [[["text", "朝"], None]], (("note", "sample"), ("label", "other"))))
    yield f"INSERT INTO {table} (id, label) VALUES (5, '') RETURNING *", insert(tag, ("id", "label"), [["5", ["text", ""]]])
    yield (f"INSERT INTO {table} (label, id, note) VALUES (-2147483648, 5, 0) {tail}",
           insert(tag, ("label", "id", "note"), [["-2147483648", "5", "0"]]))


def negatives(tag):
    rejection = "An inserted cell must match its target native kind."
    yield insert(tag, ("id",), [[["text", "abc"]]]), rejection
    yield insert(tag, ("label",), [["2147483648"]]), "Integer is outside the signed 32-bit range."
    wrong_kind = insert(tag, ("id",), [[["text", "abc"]]])
    wrong_kind["written"] = [["text-slot", 0, 0]]
    yield wrong_kind, "An INSERT write slot must belong to the target."
    wrong_occurrence = insert(tag, ("label",), [[["text", "abc"]]])
    wrong_occurrence["written"] = [["text-slot", 1, 1]]
    yield wrong_occurrence, "An INSERT write slot must belong to the target."
    yield insert(tag, ("label", "label"), [[["text", "abc"], ["text", "def"]]]), "An INSERT cannot repeat a write slot."
    yield insert(tag, ("id", "label"), [["3"]]), "Every inserted row must match the target width."


def exploratory(tag, seed, count):
    rng = random.Random(seed)
    conversions = random.Random(seed + 1)

    def choose(column):
        if column == "id":
            return rng.choice((None, str(rng.randint(-30, 30))))
        return rng.choice((None, *(["text", payload] for payload in TEXT)))

    for index in range(count):
        columns = ["label", *rng.sample(("id", "note"), k=rng.randrange(3))]
        rng.shuffle(columns)
        values = [[choose(name) for name in columns] for _ in range(rng.randint(1, 4))]
        if index % 6 == 0:
            values[0][columns.index("label")] = str(conversions.randrange(-2147483648, 2147483648))
        if rng.getrandbits(1):
            values.append(values[0][:])
        returned = [(rng.choice(HEADING), rng.choice(("name", '値 " quoted', "other"))) for _ in range(rng.randint(1, 5))]
        yield insert(tag, columns, values, returned)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, point in enumerate(product(b["layout"], b["payload"], b["rows"])):
        item = finite(tag, *point)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(analyzed(tag)):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def explicit(cell, kind):
    if cell is None:
        return None
    if isinstance(cell, str):
        value = int(cell)
        return str(value) if kind == "text" else value
    require(kind == "text" and cell[0] == "text" and isinstance(cell[1], str), "postgres-text-insert-unknown-cell", cell)
    return cell[1]


def expected(item):
    destination = item["target"]
    columns = [HEADING.index(address(term, destination)) for term in item["written"]]
    return_slots = [HEADING.index(address(term, destination)) for term, _ in item["returning"]]
    new = []
    for values in item["rows"]:
        row = [None, *DEFAULTS]
        for index, cell in zip(columns, values):
            row[index] = explicit(cell, COLUMN_TYPES[index])
        new.append(row)
    return observation({
        "status": "mutation", "count": len(new), "columns": len(return_slots),
        "fields": [[label, COLUMN_TYPES[index]] for index, (_, label) in zip(return_slots, item["returning"])],
        "rows": [[row[index] for index in return_slots] for row in new],
        "before": INITIAL, "after": INITIAL + new, "restored": INITIAL,
    })


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-insert-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-text-insert-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "mutation", "postgres-text-insert-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-text-insert-fault-native")


def sample_forms(forms, reply, *_):
    _, _, _, _, (rows, fields), layout = graph(reply)
    forms["written/" + ",".join(fields)] += 1
    if fields != sorted(fields, key=HEADING.index):
        forms["write-reordered"] += 1
    if len(set(label for _, label in layout)) < len(layout):
        forms["duplicate-return-label"] += 1
    if len(set(map(json.dumps, rows))) < len(rows):
        forms["repeated-row"] += 1
    for row in rows:
        for name, cell in zip(fields, row):
            if cell is None:
                forms["null/" + name] += 1
            elif isinstance(cell, list):
                forms["text/" + json.dumps(cell[1], ensure_ascii=False)] += 1
            elif name != "id":
                forms["assignment/int4-to-text"] += 1


def required_forms(*_):
    return {"written/label", "written/id,label", "written/label,id", "written/label,note", "written/note,label",
            "write-reordered", "duplicate-return-label", "repeated-row", "null/id", "null/label", "null/note", "assignment/int4-to-text"} | {
        "text/" + json.dumps(payload, ensure_ascii=False) for payload in TEXT
    }


def controls():
    item = finite("pg-17.2", "text-integer", "empty", "repeated")
    prediction = expected(item)
    check_observation(item, prediction, "known explicit empty text")
    altered = {**prediction, "count": 1, "rows": prediction["rows"][:1]}
    try:
        check_observation(item, altered, "drop one repeated row")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-insert-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-insert-comparator-accepted")
    return {"healthy": prediction, "lostRepeatedRow": altered}


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Mutation/"
    return [
        RenderFault("reverse-inserted-text-cells", path + "InsertRow.php", "foreach ($this->values as $item)",
                    "foreach (array_reverse($this->values) as $item)", finite(tag, "two-text", "nonempty", "single"),
                    "meaning-mismatch"),
        RenderFault("erase-empty-inserted-text", path + "InsertCell.php",
                    "$this->value instanceof TextLiteral => $this->value->toString(),",
                    "$this->value instanceof TextLiteral => $this->value->value === '' ? 'NULL' : $this->value->toString(),",
                    finite(tag, "text", "empty", "single"), "meaning-mismatch"),
        RenderFault("omit-repeated-text-row", path + "Insert.php", "$rows[] = $row->toString();",
                    "if ($rows === []) { $rows[] = $row->toString(); }", finite(tag, "text", "nonempty", "repeated"),
                    "meaning-mismatch"),
        RenderFault("discard-int4-values-assignment", path + "InsertCell.php",
                    "$this->value instanceof IntegerLiteral => 'CAST(' . $this->value->decimal . ' AS pg_catalog.int4)',",
                    "$this->value instanceof IntegerLiteral => 'CAST(0 AS pg_catalog.int4)',",
                    finite(tag, "text", "int4:-27", "single"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
