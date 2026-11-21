"""Check that the relational oracle detects changes in the meaning of valid SQL."""

import difflib
import time

from run import Failure, Worker, digest, require, shape, write_json
from sqlite_relational_cases import case, LEFT, RIGHT, JOINS


def check(args, db, path, inspect):
    root = path / "relational-faults"
    root.mkdir()
    relative = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    original = (args.candidate / "packages" / relative).read_text()
    plan = [
        ("occurrence", "$this->alias($expression->source)", "$this->alias(0)",
         case(joins=[["INNER", RIGHT, None]]), "meaning-mismatch"),
        ("outer-rows", "$join->kind->value", "'INNER'",
         case(joins=[["FULL", RIGHT, JOINS[1]]]), "meaning-mismatch"),
        ("filter", "' WHERE ' . $this->expression($select->where, $source, $select->joins)", "''",
         case(joins=[["INNER", RIGHT, None]], where=["null"]), "meaning-mismatch"),
        ("ordering", "$term->descending ? ' DESC' : ' ASC'", "$term->descending ? ' ASC' : ' DESC'",
         case(source=LEFT, columns=[[["column", 0], "key"]], orders=[[1, False, True]]), "relational-order-mismatch"),
    ]
    results = []
    with Worker(args, time.monotonic() + 45) as control:
        for _, _, _, item, _ in plan:
            inspect(control, db, item)
    for label, before, after, item, signal in plan:
        require(original.count(before) == 1, "relational-fault-patch-anchor", label)
        overlay = root / label
        file = overlay / relative
        file.parent.mkdir(parents=True)
        changed = original.replace(before, after, 1)
        file.write_text(changed)
        patch = "".join(difflib.unified_diff(original.splitlines(True), changed.splitlines(True), relative, relative))
        require(bool(patch), "relational-fault-not-applied", label)
        (overlay / "injected.diff").write_text(patch)
        with Worker(args, time.monotonic() + 30, overlay) as worker:
            try:
                inspect(worker, db, item)
            except Failure as error:
                result = {"case": label, "expected": signal, "observed": error.signal, "control": "passed",
                          "input": item, "source": digest(args.candidate / "packages" / relative),
                          "mutated": digest(file), "patch": f"relational-faults/{label}/injected.diff"}
                write_json(overlay / "result.json", result)
                require(error.signal == signal, "relational-wrong-fault-signal", result)
                results.append(result)
            else:
                raise Failure("relational-fault-undetected", label)
    label = "restore"
    relative = "sql-semantics-sqlite/src/Model/Statement/Snapshot.php"
    artifact = args.candidate / "packages" / relative
    original = artifact.read_text()
    before = "throw new InvalidConstruction('Semantic values cannot be restored without construction.');"
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
                   "mutated": digest(file), "patch": f"relational-faults/{label}/injected.diff"}
        write_json(overlay / "result.json", witness)
        require(error.signal == "shape-mismatch" and "Value primitive differs" in error.detail["message"],
                "relational-wrong-fault-signal", witness)
        results.append(witness)
    else:
        raise Failure("relational-fault-undetected", label)
    return results
