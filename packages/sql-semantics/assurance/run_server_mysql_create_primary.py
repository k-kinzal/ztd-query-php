"""Compare resolved signed MySQL primary keys with native index and constraint effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_create_table import check_value
from run_server_join import main
from run_server_mysql_create_signed import PHYSICAL_BYTES, signed
from run_server_mysql_create_varchar import (
    DIAGNOSTIC, OBSERVER, TABLE, declaration, diagnostic_fields, expected as expected_declaration,
    observation, observer_request as declaration_request, prepare, check_preparation,
    sample_forms as sample_declaration_forms, specifications as declaration_specifications, text_column,
)
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-create-primary"
CONTRACT = "3e1e5737b0d8101fe7c46fcecce8396f73002aacbb8727ce2c024b628f0e9c91"
OLD = ("mysql-5.6.51", "mysql-5.7.44")
OWNER = "A primary key must reference an existing required signed column."
SEMANTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"
FIELDS = ("TINYINT", "BIGINT", "MEDIUMINT", "SMALLINT")
UNKEYED = {"signed": ["c4", "INTEGER", True, None], "varchar": text_column("c4", 2, True, ["text", "𐐷 "])}


def specifications():
    path = TOOL / "mysql-create-primary-v2.json"
    require(digest(path) == CONTRACT, "mysql-create-primary-contract-mismatch")
    _, profiles = declaration_specifications()
    return json.loads(path.read_text()), profiles


def description(tag, fields, terms, optional=False, occupied=False):
    item = declaration(tag, fields, optional, occupied)
    item["create"]["primaryKey"] = terms
    return item


def terms(order, direction):
    return [[slot, direction == "alternating-descending" and at % 2 == 0] for at, slot in enumerate(order)]


def finite_case(tag, order, direction, default, optional, occupied, nonkey):
    fields = [[f"c{slot}", storage, False, None if slot in order and default == "required-absent" else literal(slot - 3)]
              for slot, storage in enumerate(FIELDS)]
    return description(tag, [*fields, UNKEYED[nonkey]], terms(order, direction), optional, occupied)


def finite_points(spec, tag):
    b = spec["finite"]
    vintage = "legacy" if tag in OLD else "current"
    return product(map(tuple, b["termOrders"]), b["directions"][vintage], b["defaults"], b["ifNotExists"], b["occupied"], b["nonKey"])


def finite_ids(_, spec, tag):
    points = set(finite_points(spec, tag))
    require(len(points) == spec["finite"]["identities"]["legacy" if tag in OLD else "current"],
            "mysql-create-primary-finite-size")
    return points


def finite_id(_, response, item):
    model = response["create"]
    order = tuple(part[0] for part in model["primaryKey"])
    directions = [part[1] for part in model["primaryKey"]]
    direction = "ascending" if not any(directions) else "alternating-descending"
    require(model["primaryKey"] == terms(order, direction), "mysql-create-primary-finite-direction", model)
    nonkey = [kind for kind, field in UNKEYED.items() if field == model["columns"][-1]]
    require(len(nonkey) == 1, "mysql-create-primary-finite-nonkey", model)
    absent = [model["columns"][slot][3] is None for slot in order]
    require(len(set(absent)) == 1, "mysql-create-primary-finite-defaults", model)
    return order, direction, "required-absent" if absent[0] else "literal", model["ifNotExists"], item["occupied"], nonkey[0]


def syntax(tag, clause):
    return {"dialect": "mysql", "release": tag, "sql": f"CREATE TABLE {TABLE} ({clause})", "declarations": []}


def analyzed(tag):
    c0 = ["c0", "INTEGER", False, None]
    c1 = ["c1", "BIGINT", True, None]
    implicit = [*c0[:3], literal(0)] if tag == OLD[0] else c0
    yield syntax(tag, "c0 INTEGER PRIMARY KEY, c1 BIGINT"), description(tag, [c0, c1], [[0, False]])
    yield (syntax(tag, "c0 INTEGER NOT NULL DEFAULT -7, c1 BIGINT, CONSTRAINT custom PRIMARY KEY (C0)"),
           description(tag, [[*c0[:3], literal(-7)], c1], [[0, False]]))
    yield syntax(tag, "c0 INTEGER, c1 BIGINT, PRIMARY KEY (c0)"), description(tag, [implicit, c1], [[0, False]])
    yield syntax(tag, "c0 INTEGER NOT NULL, c1 BIGINT, PRIMARY KEY (c0)"), description(tag, [c0, c1], [[0, False]])
    if tag == OLD[0]:
        for suffix in ("NULL", "DEFAULT NULL"):
            yield syntax(tag, f"c0 INTEGER {suffix}, c1 BIGINT, PRIMARY KEY (c0)"), description(tag, [implicit, c1], [[0, False]])
    yield (syntax(tag, "c0 INTEGER NOT NULL DEFAULT -7, c1 BIGINT NOT NULL DEFAULT 9, PRIMARY KEY (c1 DESC, c0 ASC)"),
           description(tag, [[*c0[:3], literal(-7)], ["c1", "BIGINT", False, literal(9)]], [[1, tag not in OLD], [0, False]]))
    yield syntax(tag, "c0 INTEGER NOT NULL, c1 BIGINT, PRIMARY KEY custom (c0)"), description(tag, [c0, c1], [[0, False]])
    fields = [[f"c{i}", "SMALLINT", False, literal(i)] for i in range(16)]
    names = ", ".join(column[0] for column in fields)
    sql = ", ".join(f"{name} SMALLINT NOT NULL DEFAULT {default[1]}" for name, _, _, default in fields)
    yield syntax(tag, f"{sql}, PRIMARY KEY ({names})"), description(tag, fields, [[i, False] for i in range(16)])


def negatives(tag):
    good = ["c0", "INTEGER", False, literal(9)]
    yield description(tag, [good], []), "A primary key needs a nonempty ordered term list."
    yield description(tag, [good], [[i, False] for i in range(17)]), "A primary key can have at most 16 column positions."
    yield description(tag, [good], [[-1, False]]), "A primary key needs a column slot from 0 through 1016."
    yield description(tag, [good], [[0, False], [0, False]]), "A primary key cannot repeat a column position."
    yield description(tag, [good], [[1, False]]), OWNER
    yield description(tag, [["c0", "INTEGER", True, None]], [[0, False]]), OWNER
    yield description(tag, [text_column("c0", 8, False, None)], [[0, False]]), OWNER
    yield syntax(tag, "c0 INTEGER PRIMARY KEY, c1 INTEGER, PRIMARY KEY (c1)"), {
        "exception": SEMANTIC, "message": "Multiple primary key defined.",
    }
    yield syntax(tag, "c0 INTEGER NOT NULL, PRIMARY KEY (c0(1))"), "A primary key requires full named column parts."
    if tag in OLD:
        yield description(tag, [good], [[0, True]]), "The grammar release does not store descending primary key parts."
    if tag == OLD[0]:
        yield syntax(tag, "c0 INTEGER DEFAULT NULL PRIMARY KEY"), "A MySQL NOT NULL column cannot declare a NULL default."
    else:
        yield syntax(tag, "c0 INTEGER NULL, PRIMARY KEY (c0)"), {
            "exception": SEMANTIC, "message": "All parts of a PRIMARY KEY must be NOT NULL; column c0 is declared NULL.",
        }


def seeded(tag, seed, count):
    rng = Random(seed)
    kinds = tuple(PHYSICAL_BYTES)
    for i in range(count):
        width = rng.randint(1, 8)
        fields = []
        for slot in range(width):
            kind = kinds[(i + slot) % len(kinds)]
            fields.append([f"c{slot}", kind, False, rng.choice((None, signed(rng.randint(-9, 9))))])
        if i % 3 == 0:
            fields.append(text_column(f"c{width}", 2, True, ["text", "𐐷 "]))
        order = rng.sample(range(width), rng.randint(1, width))
        positions = [[slot, bool(rng.getrandbits(1)) and tag not in OLD] for slot in order]
        yield description(tag, fields, positions, bool(i & 1), bool(i & 2))


def requests(_, tag, spec):
    for index, point in enumerate(finite_points(spec, tag)):
        item = finite_case(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = tuple(analyzed(tag))
    gate = len(spec["analysis"]) - (0 if tag == OLD[0] else 2)
    require(len(cases) == gate, "mysql-create-primary-analysis-incomplete")
    for index, (request, presumption) in enumerate(cases):
        yield f"analysis/{index}", request, presumption, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, rejection) in enumerate(negatives(tag)):
        boundary = {"exception": DIAGNOSTIC, "message": rejection} if isinstance(rejection, str) else rejection
        yield f"negative/{index}", item, None, boundary


def observer_request(item, sql):
    return {**declaration_request(item, sql), "observePrimaryIndex": True}


def expected(item):
    result = expected_declaration(item)
    occupied = item["occupied"]
    model = item["create"]
    primary = [] if occupied else [
        ["PRIMARY", 0, seq, model["columns"][slot][0], "D" if descending else "A", None, "", "BTREE"]
        for seq, (slot, descending) in enumerate(model["primaryKey"], 1)
    ]
    result["catalog"] = {**result["catalog"], "primaryIndex": primary}
    result["afterRollback"] = result["catalog"]
    result["secondInsertion"] = None
    if result["status"] != "executed":
        return result
    if occupied:
        result["secondInsertion"] = {"status": "inserted", **diagnostic_fields([])}
        result["stored"] *= 2
        return result
    probe = result["defaultInsertion"]
    value = "-".join(str(probe["values"][slot]) for slot, _ in model["primaryKey"])
    index = "PRIMARY" if item["release"] in OLD else "ir_create_target.PRIMARY"
    duplicate = ["Error", 1062, f"Duplicate entry '{value}' for key '{index}'"]
    result["secondInsertion"] = {"status": "23000", **diagnostic_fields([*probe["diagnostics"], duplicate])}
    return result


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == prediction["status"], "mysql-create-primary-native-status", witness)
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "executed" and witness["observed"] != witness["expected"],
            "mysql-create-primary-fault-native", witness)


def required_forms(_, tag):
    direction = {"direction/descending"} if tag not in OLD else set()
    return {"key/single", "key/composite", "direction/ascending", "slot/first", "slot/later",
            "width/multiple", "type/varchar", "default/absent", "default/integer", "prior/True", "prior/False",
            *(f"type/{kind.lower()}" for kind in PHYSICAL_BYTES), *direction}


def sample_forms(forms, response, engine, item):
    sample_declaration_forms(forms, response, engine, item)
    key = response["create"]["primaryKey"]
    forms["key/" + ("single" if len(key) == 1 else "composite")] += 1
    for slot, descending in key:
        forms["direction/" + ("descending" if descending else "ascending")] += 1
        forms["slot/" + ("first" if slot == 0 else "later")] += 1


def controls():
    item = finite_case("mysql-9.1.0", (1, 0), "alternating-descending", "literal", False, False, "varchar")
    prediction = expected(item)
    check_observation(item, prediction, "known primary key")
    schema = prediction["catalog"]
    changes = {
        "lost-key-layout": {**prediction, "catalog": {**schema, "primaryIndex": []}},
        "ignored-duplicate": {**prediction, "secondInsertion": {"status": "inserted", **diagnostic_fields([])}},
    }
    detected = {}
    for name, observation in changes.items():
        try:
            check_observation(item, observation, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "mysql-create-primary-control-signal", error.detail)
            detected[name] = error.signal
        else:
            raise Failure("mysql-create-primary-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": changes, "detections": detected}


def faults(_, tag):
    base = "sql-semantics-mysql/src/Ir/Schema/"
    path = base + "CreateTable.php"
    layout = finite_case(tag, (1, 0), "ascending", "literal", False, False, "varchar")
    field = finite_case(tag, (1,), "ascending", "literal", False, False, "signed")
    emitted = "$columns[] = 'PRIMARY KEY (' . implode(', ', $parts) . ')';"
    return [
        RenderFault("drop-native-primary-key", path, emitted, "", layout, "meaning-mismatch"),
        RenderFault("reverse-native-primary-key-terms", path, "implode(', ', $parts)", "implode(', ', array_reverse($parts))",
                    layout, "meaning-mismatch"),
        RenderFault("use-wrong-primary-owner-slot", path, "$this->columns[$term->slot]->name->quoted()", "$this->columns[0]->name->quoted()",
                    field, "meaning-mismatch"),
        RenderFault("change-native-primary-default", base + "TableColumn.php", "' DEFAULT ' . $this->default->decimal", "' DEFAULT 0'",
                    field, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
