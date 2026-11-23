"""Compare checked MySQL VARCHAR declarations with native schema, storage and transaction effects."""

from collections import Counter
from itertools import product
import json
from random import Random
import sys

from mysql_varchar_storage import truncated
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_create_table import (
    OBSERVER, check_value, prepare as prepare_native, check_preparation as check_native_preparation,
    specifications as native_specifications,
)
from run_server_join import main
from server_create_table_cases import case, default_value, observation
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-create-varchar"
CONTRACT = "60452105e3f68e8e35ffb7fa146a364193bc05c7c879fd1b5388a758e358d994"
DIAGNOSTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
TABLE = "ir_fixture.ir_create_target"
COLLATION = "utf8mb4_bin"
FORMS = {
    "zero-empty": (0, False, ["text", ""]),
    "zero-absent": (0, False, None),
    "zero-null": (0, True, ["null"]),
    "zero-spaces": (0, True, ["text", " "]),
    "wide-absent": (8, True, None),
    "wide-required": (8, False, None),
    "wide-quoted": (8, False, ["text", "q'u"]),
    "wide-unicode": (2, False, ["text", "é𐐷"]),
    "wide-space": (2, True, ["text", "a "]),
    "wide-truncated-space": (1, True, ["text", "a  "]),
}
SIGNED = ["c1", "SMALLINT", False, literal(-7)]


def specifications():
    path = TOOL / "mysql-create-varchar-v1.json"
    require(digest(path) == CONTRACT, "mysql-create-varchar-contract-mismatch")
    _, profiles = native_specifications()
    return json.loads(path.read_text()), {"mysql": profiles["mysql"]}


def schema_collation(tag):
    if tag in {"mysql-5.6.51", "mysql-5.7.44"}:
        return "latin1_swedish_ci"
    return "utf8mb4_0900_ai_ci"


def check_preparation(engine, environment, prepared):
    check_native_preparation(engine, environment, prepared)
    raw_version, *_ = environment["database"]
    same(schema_collation("mysql-" + raw_version.split("-")[0]), environment["schemaCollation"])
    same([16384, int(not raw_version.startswith("5.6.51")), 1, 64, "en_US"], environment["storageSettings"])


def prepare(engine, db):
    return prepare_native(engine, db)


def text_column(name, length, nullable, default):
    return [name, ["varchar", length, COLLATION], nullable, default]


def declaration(tag, fields, optional=False, occupied=False, table_collation=None):
    result = case("mysql", tag, fields, optional, occupied)
    if table_collation is not None:
        result["create"]["tableCollation"] = table_collation
    return result


def finite_case(tag, form, optional, occupied, mixed):
    field = text_column("c0", *FORMS[form])
    return declaration(tag, [SIGNED, field] if mixed else [field], optional, occupied)


def finite_points(spec):
    finite = spec["finite"]
    return product(finite["forms"], finite["existsPolicy"], finite["occupied"], finite["mixed"])


def finite_ids(_, spec, _tag):
    points = set(finite_points(spec))
    require(len(points) == spec["finite"]["identities"] and set(FORMS) == set(spec["finite"]["forms"]),
            "mysql-create-varchar-finite-size")
    return points


def finite_id(_, response, item):
    model = response["create"]
    fields = model["columns"]
    mixed = len(fields) == 2
    require(len(fields) == 1 or (mixed and fields[0] == SIGNED), "mysql-create-varchar-finite-layout", fields)
    name, (kind, length, collation), nullable, default = fields[-1]
    require((name, kind, collation) == ("c0", "varchar", COLLATION) and "tableCollation" not in model,
            "mysql-create-varchar-finite-column", model)
    matches = [key for key, form in FORMS.items() if form == (length, nullable, default)]
    require(len(matches) == 1, "mysql-create-varchar-finite-form", model)
    return matches[0], model["ifNotExists"], item["occupied"], mixed


def analysis(tag, clause):
    return {"dialect": "mysql", "release": tag, "sql": f"CREATE TABLE {TABLE} {clause}", "declarations": []}


def analyzed(tag):
    yield (analysis(tag, "(c0 VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'é𐐷')"),
           finite_case(tag, "wide-unicode", False, False, False))
    yield (analysis(tag, "(c0 VARCHAR(8)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"),
           declaration(tag, [text_column("c0", *FORMS["wide-absent"])], table_collation=COLLATION))
    yield (analysis(tag, "(c1 SMALLINT NOT NULL DEFAULT -7, c0 VARCHAR(8) COLLATE utf8mb4_bin) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"),
           declaration(tag, [SIGNED, text_column("c0", *FORMS["wide-absent"])], table_collation=COLLATION))
    yield (analysis(tag, "(c0 VARCHAR(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL)"),
           finite_case(tag, "zero-absent", False, False, False))
    yield (analysis(tag, "(c0 VARCHAR(0) COLLATE utf8mb4_bin NULL DEFAULT NULL)"),
           finite_case(tag, "zero-null", False, False, False))
    yield (analysis(tag, "(c0 VARCHAR(1) COLLATE utf8mb4_bin DEFAULT 'a  ')"),
           finite_case(tag, "wide-truncated-space", False, False, False))


def negatives(tag):
    yield declaration(tag, [text_column("c0", 1, False, literal(7))]), "A signed default requires native integer storage."
    yield declaration(tag, [["c0", "INTEGER", True, ["text", "7"]]]), "A text default requires native VARCHAR storage."
    yield declaration(tag, [text_column("c0", 1, True, ["text", "a𐐷"])]), "A VARCHAR default cannot discard significant characters."
    yield declaration(tag, [text_column("c0", 8, False, ["null"])]), "A MySQL NOT NULL column cannot declare a NULL default."
    yield declaration(tag, [text_column("c0", 8192, True, None), text_column("c1", 8191, True, None)]), (
        "The declared logical record cannot exceed 65535 bytes.")
    yield declaration(tag, [text_column("c0", 16384, True, None)]), "A utf8mb4 VARCHAR length must be between 0 and 16383 characters."
    yield analysis(tag, "(c0 VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci)"), (
        "The text slot requires an explicit utf8mb4_bin collation.")


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        fields = []
        for column in range(rng.randint(1, 5)):
            name = f"c{column}"
            if column and rng.choice((True, False)):
                fields.append([name, "SMALLINT", rng.choice((True, False)), literal(rng.randint(-32768, 32767))])
                continue
            value = "".join(rng.choices(("A", "é", "𐐷", "'", "\\", "\x00", " "), k=rng.randint(0, 8)))
            length = len(value) + rng.choice((0, 1, 63, 64))
            default = ["text", value + " " * rng.randint(0, 4)]
            fields.append(text_column(name, length, rng.choice((True, False)), default))
        if index < len(FORMS):
            fields[0] = text_column("c0", *tuple(FORMS.values())[index])
        if index == 0:
            fields.append(text_column("c5", 1, False, ["text", "\x00"]))
        if index == 1:
            fields.extend([text_column("c5", 63, True, None), text_column("c6", 64, True, None)])
        rng.shuffle(fields)
        yield declaration(tag, fields, bool(index & 1), bool(index & 2), COLLATION if index & 4 else None)


def requests(_, tag, spec):
    for index, point in enumerate(finite_points(spec)):
        item = finite_case(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = tuple(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "mysql-create-varchar-analysis-incomplete")
    for index, (request, meaning) in enumerate(cases):
        yield f"analysis/{index}", request, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    edges = (
        [text_column("c0", 16383, True, None)],
        [text_column("c0", 8191, True, None), text_column("c1", 8191, True, None)],
    )
    for index, fields in enumerate(edges):
        item = declaration(tag, fields)
        yield f"edge/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def observer_request(item, sql):
    return {"sql": sql, "occupied": item["occupied"], "observeNativeDeclaration": True}


def varchar_value(length, default):
    if default is None or default == ["null"]:
        return None, None, None
    stored, level = truncated(default[1], length)
    catalog = "".join(ch if ord(ch) <= 0xffff else "?" for ch in stored)
    return stored, catalog, level


def create_values(fields):
    columns, stored, declaration_diagnostics, insertion_diagnostics = [], [], [], []
    signed = {"TINYINT": "tinyint", "SMALLINT": "smallint", "MEDIUMINT": "mediumint", "INTEGER": "int", "BIGINT": "bigint"}
    for index, (name, storage, nullable, default) in enumerate(fields, 1):
        if isinstance(storage, list):
            _, length, collation = storage
            value, catalog, level = varchar_value(length, default)
            columns.append([index, name, "varchar", nullable, catalog, catalog is not None, length, collation])
            if level is not None:
                declaration_diagnostics.append([level, 1265, f"Data truncated for column '{name}' at row 1"])
            implicit = ""
        else:
            value = default_value(storage, default)
            columns.append([index, name, signed[storage], nullable, value, value is not None])
            implicit = 0
        if value is None and not nullable:
            insertion_diagnostics.append(["Warning", 1364, f"Field '{name}' doesn't have a default value"])
            value = implicit
        stored.append(value)
    return columns, stored, declaration_diagnostics, insertion_diagnostics


def diagnostic_fields(diagnostics):
    return {"diagnostics": diagnostics, "warningCount": len(diagnostics)}


def expected(item):
    model = item["create"]
    occupied = item["occupied"]
    rejected = occupied and not model["ifNotExists"]
    fields = [["prior", "INTEGER", False, literal(97)]] if occupied else model["columns"]
    columns, values, ddl_diagnostics, insert_diagnostics = create_values(fields)
    if occupied:
        ddl_diagnostics = [["Error" if rejected else "Note", 1050, "Table 'ir_create_target' already exists"]]
    table_collation = schema_collation(item["release"])
    if not occupied:
        table_collation = model.get("tableCollation", table_collation)
    row_format = "Compact" if item["release"] == "mysql-5.6.51" else "Dynamic"
    catalog = {"columns": columns, "engine": "InnoDB", "tableCollation": table_collation, "rowFormat": row_format}
    default_insertion = None if rejected else {
        "status": "inserted", "values": values, **diagnostic_fields(insert_diagnostics),
    }
    return {"status": "42S01" if rejected else "executed", "active": False, "catalog": catalog,
            "defaultInsertion": default_insertion, "afterRollback": catalog, "markerAfterRollback": [0, 7],
            "stored": [] if rejected else [values], **diagnostic_fields(ddl_diagnostics)}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == prediction["status"], "mysql-create-varchar-native-status", witness)
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "executed" and witness["observed"] != witness["expected"],
            "mysql-create-varchar-fault-native", witness)


def required_forms(*_):
    return {"type/smallint", "type/varchar", "width/single", "width/multiple", "prior/True", "prior/False",
            "optional/True", "optional/False", "nullability/True", "nullability/False", "default/absent",
            "default/null", "default/integer", "default/text", "length/0", "length/63", "length/64",
            "default/nul", "default/multibyte", "default/padded", "table-collation/explicit", "table-collation/inherited"}


def sample_forms(forms: Counter, response, _engine, item):
    model = response["create"]
    fields = model["columns"]
    forms["width/" + ("single" if len(fields) == 1 else "multiple")] += 1
    forms[f"prior/{item['occupied']}"] += 1
    forms[f"optional/{model['ifNotExists']}"] += 1
    forms["table-collation/" + ("explicit" if "tableCollation" in model else "inherited")] += 1
    for _, storage, nullable, default in fields:
        forms[f"nullability/{nullable}"] += 1
        forms["default/" + ("absent" if default is None else default[0])] += 1
        if isinstance(storage, str):
            forms["type/" + storage.lower()] += 1
            continue
        _, length, _ = storage
        forms["type/varchar"] += 1
        forms[f"length/{length}"] += 1
        if default is not None and default[0] == "text":
            value = default[1]
            for category, present in (("nul", "\x00" in value), ("multibyte", not value.isascii()), ("padded", len(value) > length)):
                if present:
                    forms[f"default/{category}"] += 1


def controls():
    item = finite_case("mysql-8.4.7", "wide-truncated-space", False, False, True)
    prediction = expected(item)
    check_observation(item, prediction, "known VARCHAR declaration")
    catalog = prediction["catalog"]
    altered = {
        "discard-create-diagnostics": {**prediction, **diagnostic_fields([])},
        "change-stored-value": {**prediction, "stored": [[-7, "a "]]},
        "change-table-collation": {**prediction, "catalog": {**catalog, "tableCollation": COLLATION}},
        "lose-transaction-marker": {**prediction, "markerAfterRollback": [0]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "mysql-create-varchar-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("mysql-create-varchar-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def faults(_, tag):
    base = "sql-semantics-mysql/src/Ir/Schema/"
    field = base + "TableColumn.php"
    storage = base + "CharacterStorage.php"
    quoted = finite_case(tag, "wide-quoted", False, False, True)
    spacious = finite_case(tag, "wide-space", False, False, False)
    required = finite_case(tag, "wide-required", False, False, False)
    return [
        RenderFault("change-assigned-default", field, "$this->default->toString()", "(new TextLiteral('x'))->toString()", quoted, "meaning-mismatch"),
        RenderFault("change-native-collation", storage, "$this->collation->value", "'utf8mb4_general_ci'", quoted, "meaning-mismatch"),
        RenderFault("change-native-capacity", storage, "$this->length", "8", spacious, "meaning-mismatch"),
        RenderFault("make-required-column-nullable", field, "($this->nullable ? ' NULL' : ' NOT NULL')", "' NULL'", required, "meaning-mismatch"),
        RenderFault("reverse-mixed-column-order", base + "CreateTable.php", "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    quoted, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
