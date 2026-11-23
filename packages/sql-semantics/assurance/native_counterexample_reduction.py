"""Retain and reduce comparable direct native SELECT counterexamples."""

from collections import Counter
from itertools import islice
import json

from counterexample_reduction import reduce
from evidence_transcript import canonical
from run import TOOL, Failure, digest, require, write_json

CONTRACT = "native-counterexample-reduction-v1.json"
DIGEST = "580c71146927250733fc79e8dcc062180d867efb113d486fab38dce4068565ef"
CONTROL_RESULTS = {
    "native-reduce-stable": "reproduced",
    "native-reduce-different-comparison": "no-confirmed-smaller-input",
    "native-reduce-rejected-construction": "no-confirmed-smaller-input",
    "native-reduce-flaky": "no-confirmed-smaller-input",
    "native-reduce-proposal-budget": "no-confirmed-smaller-input",
    "native-reduce-ineligible": "not-applicable",
}
KEYS = {"dialect", "release", "source", "columns", "where", "distinct", "orderBy", "limit"}


def references_parameters(node):
    if isinstance(node, list):
        return (bool(node) and node[0] == "parameter") or any(map(references_parameters, node))
    if isinstance(node, dict):
        return any(map(references_parameters, node.values()))
    return False


def eligible(item, expected):
    return (isinstance(item, dict) and item == expected and item.keys() <= KEYS
            and isinstance(item.get("source"), dict) and isinstance(item.get("columns"), list)
            and len(item["columns"]) >= 2 and all(isinstance(field, list) and len(field) == 2
                                                 and isinstance(field[1], str) for field in item["columns"])
            and not item.get("distinct") and not item.get("orderBy") and item.get("limit") is None
            and not references_parameters(item))


def comparison_family(detail):
    if not isinstance(detail, dict):
        return None
    expected, observed = detail.get("expected"), detail.get("observed")
    if not all(isinstance(snapshot, dict) and snapshot.get("status") == "rows"
               and isinstance(snapshot.get("fields"), list) and isinstance(snapshot.get("rows"), list)
               for snapshot in (expected, observed)):
        return None
    fields = canonical(expected["fields"]) != canonical(observed["fields"])
    rows = Counter(map(canonical, expected["rows"])) != Counter(map(canonical, observed["rows"]))
    return {(True, False): "metadata", (False, True): "rows", (True, True): "metadata-and-rows"}.get((fields, rows))


def proposals(item):
    fields = item["columns"]
    for position in range(len(fields)):
        yield {**item, "columns": fields[:position] + fields[position + 1:]}


def record(path, observation, expected, probe):
    reduction_path = path.with_suffix(".reduction.json")
    write_json(path, {**observation, "shrunk": None, "reductionEvidence": reduction_path.name})
    original = observation["original"]
    signal = observation["signal"]
    family = comparison_family(observation["detail"])
    receipt = {"originalDigest": digest(path), "contract": DIGEST, "signal": signal, "family": family,
               "status": "not-applicable", "attempts": [], "shrunk": None}

    def verify_failure(item):
        try:
            probe(item)
        except Failure as failure:
            actual = comparison_family(failure.detail)
            if failure.signal == signal and actual != family:
                raise Failure("different-observation-failure", {"family": actual, "detail": failure.detail}) from failure
            raise

    try:
        contract = TOOL / CONTRACT
        require(digest(contract) == DIGEST, "native-reduction-contract-mismatch")
        specification = json.loads(contract.read_text())
        if family is not None and signal in specification["admittedSignals"] and eligible(original, expected):
            candidates = lambda value: islice(proposals(value), specification["maximumProposals"])
            receipt.update(reduce(original, signal, candidates, verify_failure, specification["maximumReductionProbes"], receipt["attempts"]))
    except Exception as error:
        receipt.update(status="reducer-failure", failure={"type": type(error).__name__, "detail": str(error)})
    write_json(reduction_path, receipt)
