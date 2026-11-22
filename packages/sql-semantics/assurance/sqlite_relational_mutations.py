"""Check that the relational oracle detects changes in the meaning of valid SQL."""

import difflib

from run import Failure, digest, require, shape, write_json
from sqlite_domain import RenderFault, check_render_faults
from sqlite_relational_cases import case, LEFT, RIGHT, JOINS


SELECT_RENDERER = "sql-semantics-sqlite/src/Ir/SelectSql.php"
RESTORATION = "sql-semantics-sqlite/src/Model/Statement/Snapshot.php"
RESTORATION_GUARD = "throw new InvalidConstruction('Semantic values cannot be restored without construction.');"


def fixtures():
    return [
        RenderFault("occurrence", SELECT_RENDERER, "$this->alias($expression->source)", "$this->alias(0)",
                    case(joins=[["INNER", RIGHT, None]]), "meaning-mismatch"),
        RenderFault("outer-rows", SELECT_RENDERER, "$join->kind->value", "'INNER'",
                    case(joins=[["FULL", RIGHT, JOINS[1]]]), "meaning-mismatch"),
        RenderFault("filter", SELECT_RENDERER, "' WHERE ' . $this->expression($select->where, $source, $select->joins)", "''",
                    case(joins=[["INNER", RIGHT, None]], where=["null"]), "meaning-mismatch"),
        RenderFault("ordering", SELECT_RENDERER, "$term->descending ? ' DESC' : ' ASC'", "$term->descending ? ' ASC' : ' DESC'",
                    case(source=LEFT, columns=[[["column", 0], "key"]], orders=[[1, False, True]]), "relational-order-mismatch"),
    ]


def check(args, db, path, inspect):
    results = check_render_faults(args, db, fixtures(), inspect)
    root = path / "relational-faults"
    root.mkdir()
    label = "restore"
    relative = RESTORATION
    artifact = args.candidate / "packages" / relative
    original = artifact.read_text()
    before = RESTORATION_GUARD
    require(original.count(before) == 1, "relational-fault-patch-anchor", label)
    overlay = root / label
    file = overlay / relative
    file.parent.mkdir(parents=True)
    altered = original.replace(before, "return;", 1)
    file.write_text(altered)
    delta = "".join(difflib.unified_diff(original.splitlines(True), altered.splitlines(True), relative, relative))
    (overlay / "injected.diff").write_text(delta)
    shape(args)
    try:
        shape(args, overlay)
    except Failure as error:
        witness = {"case": label, "expected": "shape-mismatch", "observed": error.signal, "control": "passed",
                   "input": "Allow native PHP restoration without construction", "source": digest(artifact),
                   "mutated": digest(file), "patch": f"relational-faults/{label}/injected.diff", "detail": error.detail}
        write_json(overlay / "result.json", witness)
        require(error.signal == "shape-mismatch" and "Value primitive differs" in error.detail["message"],
                "relational-wrong-fault-signal", witness)
        results.append(witness)
    else:
        raise Failure("relational-fault-undetected", label)
    return results
