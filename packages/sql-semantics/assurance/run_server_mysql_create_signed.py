"""Compare all five ordinary signed MySQL column widths and their typed defaults."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_create_table import check_value
from run_server_join import main
from run_server_mysql_create_varchar import (
    DIAGNOSTIC, OBSERVER, TABLE, check_fault_witness, check_observation, declaration, diagnostic_fields,
    expected, observer_request, observation, prepare as prepare_declarations,
    check_preparation as check_declaration_preparation, sample_forms as sample_declaration_forms, text_column,
    specifications as declaration_specifications,
)
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-create-signed"
CONTRACT = "0999b5f1f5de0aff4d2d07e9d9a42ac5c53e9e165f42ad7b8d53a7cb1d7e8312"
PHYSICAL_BYTES = {"TINYINT": 1, "SMALLINT": 2, "MEDIUMINT": 3, "INTEGER": 4, "BIGINT": 8}
TEXT = text_column("c1", 2, True, ["text", "𐐷 "])
ZERO_PADDED = ["integer", "0", 2, True]
LIMIT = 1 << 31


def interval(storage):
    bound = 1 << (8 * PHYSICAL_BYTES[storage] - 1)
    return -bound, bound - 1


def signed(value):
    return literal(value) if -LIMIT <= value < LIMIT else ["range-integer", str(value), 0, False]


def forms(storage):
    low, high = interval(storage)
    return {
        "nullable-absent": (True, None),
        "required-absent": (False, None),
        "explicit-null": (True, ["null"]),
        "lower": (True, signed(low)),
        "upper": (False, signed(high)),
        "zero": (False, ZERO_PADDED),
    }


def specifications():
    path = TOOL / "mysql-create-signed-v2.json"
    require(digest(path) == CONTRACT, "mysql-create-signed-contract-mismatch")
    spec = json.loads(path.read_text())
    ranges = {storage: {"bytes": width, "lower": str(interval(storage)[0]), "upper": str(interval(storage)[1])}
              for storage, width in PHYSICAL_BYTES.items()}
    same(ranges, spec["storage"])
    _, profiles = declaration_specifications()
    return spec, profiles


def finite_points(spec):
    finite = spec["finite"]
    return product(finite["storage"], finite["forms"], finite["ifNotExists"], finite["occupied"], finite["mixed"])


def finite_ids(_, spec, _tag):
    points = set(finite_points(spec))
    require(len(points) == spec["finite"]["identities"] and set(PHYSICAL_BYTES) == set(spec["finite"]["storage"]),
            "mysql-create-signed-finite-size")
    return points


def finite_case(tag, storage, form, optional=False, occupied=False, mixed=False):
    fields = [["c0", storage, *forms(storage)[form]]]
    if mixed:
        fields.insert(0, TEXT)
    return declaration(tag, fields, optional, occupied)


def finite_id(_, response, item):
    model = response["create"]
    fields = model["columns"]
    mixed = len(fields) == 2
    require(len(fields) == 1 or (mixed and fields[0] == TEXT), "mysql-create-signed-finite-layout", fields)
    name, storage, nullable, default = fields[-1]
    require(name == "c0" and storage in PHYSICAL_BYTES and "tableCollation" not in model, "mysql-create-signed-finite-column", model)
    matches = [key for key, form in forms(storage).items() if form == (nullable, default)]
    require(len(matches) == 1, "mysql-create-signed-finite-form", model)
    return storage, matches[0], model["ifNotExists"], item["occupied"], mixed


def analysis(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": []}


def analyzed(tag):
    for kind, form, operands, mixed in (
        ("TINYINT", "lower", "TINYINT DEFAULT -128", False),
        ("MEDIUMINT", "upper", "MEDIUMINT NOT NULL DEFAULT 8388607", False),
        ("BIGINT", "lower", "BIGINT DEFAULT -9223372036854775808", False),
        ("BIGINT", "upper", "BIGINT NOT NULL DEFAULT 9223372036854775807", True),
        ("INTEGER", "zero", "INTEGER NOT NULL DEFAULT -000", False),
        ("SMALLINT", "required-absent", "SMALLINT NOT NULL", False),
    ):
        leading = "c1 VARCHAR(2) COLLATE utf8mb4_bin DEFAULT '𐐷 ', " if mixed else ""
        yield analysis(tag, f"CREATE TABLE {TABLE} ({leading}c0 {operands})"), finite_case(tag, kind, form, mixed=mixed)


def outside_bounds():
    for storage in PHYSICAL_BYTES:
        low, high = interval(storage)
        for value in (low - 1, high + 1):
            yield storage, value


def negatives(tag):
    for storage, value in outside_bounds():
        item = declaration(tag, [["c0", storage, True, signed(value)]])
        boundary = "Range integer is outside the signed 64-bit range." if storage == "BIGINT" else (
            f"The {storage} default is outside the signed storage range.")
        yield item, boundary
    yield declaration(tag, [["c0", "BIGINT", False, ["null"]]]), "A MySQL NOT NULL column cannot declare a NULL default."
    yield analysis(tag, f"CREATE TABLE {TABLE} (c0 TINYINT UNSIGNED DEFAULT 255)"), "The column requires a signed integer storage role."


def seeded(tag, seed, count):
    rng = Random(seed)
    choices = tuple(PHYSICAL_BYTES)
    for index in range(count):
        fields = []
        for slot in range(rng.randrange(1, len(choices) + 2)):
            kind = choices[(index + slot) % len(choices)]
            low, high = interval(kind)
            choices_at_width = [signed(rng.choice((low, low + 1, high - 1, high, rng.randint(low, high)))), None]
            nullable = rng.choice((True, False))
            if nullable:
                choices_at_width.append(["null"])
            fields.append([f"c{slot}", kind, nullable, rng.choice(choices_at_width)])
        if index % 3 == 0:
            fields.append(text_column("c8", 2, True, ["text", rng.choice(("", "A ", "𐐷"))]))
        rng.shuffle(fields)
        yield declaration(tag, fields, bool(index & 1), bool(index & 2))


def requests(_, tag, spec):
    for index, point in enumerate(finite_points(spec)):
        item = finite_case(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = tuple(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "mysql-create-signed-analysis-incomplete")
    for index, (request, meaning) in enumerate(cases):
        yield f"analysis/{index}", request, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def prepare(engine, db):
    return {
        "disallowedStatements": prepare_declarations(engine, db),
        "outOfRangeDefaults": {
            sql: db.request({"sql": sql, "occupied": False, "observeNativeDeclaration": True})
            for sql in invalid_native_statements()
        },
    }


def invalid_native_statements():
    for storage, value in outside_bounds():
        yield f"CREATE TABLE {TABLE} (c0 {storage} DEFAULT {value})"


def check_preparation(engine, environment, prepared):
    check_declaration_preparation(engine, environment, prepared["disallowedStatements"])
    diagnostics = [["Warning", 1264, "Out of range value for column 'c0' at row 1"],
                   ["Error", 1067, "Invalid default value for 'c0'"]]
    native_rejection = {"status": "42000", "active": False, "catalog": None, "afterRollback": None,
                        "defaultInsertion": None, "stored": [], "markerAfterRollback": [0, 7], **diagnostic_fields(diagnostics)}
    same({sql: native_rejection for sql in invalid_native_statements()}, prepared["outOfRangeDefaults"])


def required_forms(*_):
    return {"width/single", "width/multiple", "prior/True", "prior/False", "optional/True", "optional/False", "type/varchar",
            "nullability/True", "nullability/False", "default/absent", "default/null", "default/integer", "default/range-integer",
            "bound/lower", "bound/upper", "bound/inside", *("type/" + kind.lower() for kind in PHYSICAL_BYTES)}


def sample_forms(coverage, response, engine, item):
    sample_declaration_forms(coverage, response, engine, item)
    for _, kind, _, default in response["create"]["columns"]:
        if isinstance(kind, str) and default is not None and default[0] in {"integer", "range-integer"}:
            value = int(default[1])
            low, high = interval(kind)
            comparison = "lower" if value == low else "upper" if value == high else "inside"
            coverage[f"bound/{comparison}"] += 1


def controls():
    item = finite_case("mysql-9.1.0", "BIGINT", "upper")
    prediction = expected(item)
    check_observation(item, prediction, "known BIGINT endpoint")
    altered = {**prediction, "defaultInsertion": {
        **prediction["defaultInsertion"], "values": [float(9223372036854775807)],
    }}
    try:
        check_observation(item, altered, "replace the exact integer by a floating-point approximation")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "mysql-create-signed-control-signal", failure.detail)
        detected = failure.signal
    else:
        raise Failure("mysql-create-signed-comparator-accepted")
    return {"input": item, "prediction": prediction, "roundedInteger": altered, "detection": detected}


def faults(_, tag):
    base = "sql-semantics-mysql/src/Ir/Schema/"
    field = base + "TableColumn.php"
    wide = finite_case(tag, "BIGINT", "upper", mixed=True)
    small = finite_case(tag, "TINYINT", "lower")
    required = finite_case(tag, "MEDIUMINT", "required-absent")
    type_emission = "$this->storage instanceof IntegerStorage ? $this->storage->value : $this->storage->toString()"
    return [
        RenderFault("replace-bigint-default", field, "' DEFAULT ' . $this->default->decimal", "' DEFAULT 0'", wide, "meaning-mismatch"),
        RenderFault("widen-tinyint-declaration", field, type_emission,
                    "$this->storage === IntegerStorage::Tiny ? 'SMALLINT' : (" + type_emission + ")", small, "meaning-mismatch"),
        RenderFault("make-required-signed-column-nullable", field, "($this->nullable ? ' NULL' : ' NOT NULL')", "' NULL'",
                    required, "meaning-mismatch"),
        RenderFault("reverse-mixed-signed-declaration-order", base + "CreateTable.php", "implode(', ', $columns)",
                    "implode(', ', array_reverse($columns))", wide, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
