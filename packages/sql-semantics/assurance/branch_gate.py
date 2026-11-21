"""Prepare and audit the repository rule for an independently published status."""

import argparse
import hashlib
import json
from pathlib import Path


CONTEXT = "semantics-ir/acceptance"
REF_NAME = "refs/heads/main"


def proposed_ruleset(app_id):
    return {
        "name": "SemanticsIR independent acceptance",
        "target": "branch",
        "enforcement": "disabled",
        "bypass_actors": [],
        "conditions": {"ref_name": {"include": [REF_NAME], "exclude": []}},
        "rules": [{
            "type": "required_status_checks",
            "parameters": {
                "required_status_checks": [{"context": CONTEXT, "integration_id": app_id}],
                "strict_required_status_checks_policy": True,
                "do_not_enforce_on_create": False,
            },
        }],
    }


def matching_rule(rule, expected):
    if rule.get("type") != expected["type"]:
        return False
    actual, wanted = rule.get("parameters", {}), expected["parameters"]
    return (actual.get("strict_required_status_checks_policy") is True
            and actual.get("do_not_enforce_on_create", False) is False
            and all(check in actual.get("required_status_checks", []) for check in wanted["required_status_checks"]))


def audit(app_id, rules, full_definition, repository):
    expected = proposed_ruleset(app_id)
    expected_rule = expected["rules"][0]
    matching = [rule for rule in rules
                if rule.get("ruleset_source_type") == "Repository" and rule.get("ruleset_source") == repository
                and matching_rule(rule, expected_rule)]
    if not matching:
        return "required-rule-absent"
    if full_definition is None or "bypass_actors" not in full_definition:
        return "bypass-policy-unverified"
    if full_definition.get("id") not in [rule["ruleset_id"] for rule in matching]:
        return "ruleset-identity-mismatch"
    if (full_definition.get("target") != expected["target"]
            or full_definition.get("conditions") != expected["conditions"]
            or full_definition.get("enforcement") != "active"
            or full_definition["bypass_actors"] != []
            or not any(matching_rule(rule, expected_rule) for rule in full_definition.get("rules", []))):
        return "ruleset-policy-mismatch"
    return "expected-rule-observed"


def read(path):
    data = path.read_bytes()
    return json.loads(data), hashlib.sha256(data).hexdigest()


def positive_id(text):
    number = int(text)
    if number <= 0:
        raise argparse.ArgumentTypeError("The published status needs a specific GitHub App ID.")
    return number


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    actions = parser.add_subparsers(dest="action", required=True)
    proposal = actions.add_parser("prepare")
    proposal.add_argument("--app-id", required=True, type=positive_id)
    checker = actions.add_parser("audit")
    checker.add_argument("--app-id", required=True, type=positive_id)
    checker.add_argument("--repository", required=True)
    checker.add_argument("--rules", required=True, type=Path)
    checker.add_argument("--definition", type=Path)
    args = parser.parse_args()
    if args.action == "prepare":
        print(json.dumps(proposed_ruleset(args.app_id), indent=2))
        return
    rules, rules_digest = read(args.rules)
    definition, definition_digest = (None, None) if args.definition is None else read(args.definition)
    outcome = audit(args.app_id, rules, definition, args.repository)
    print(json.dumps({
        "repository": args.repository, "branch": REF_NAME, "context": CONTEXT, "expectedApp": args.app_id,
        "effectiveRulesDigest": rules_digest, "rulesetDefinitionDigest": definition_digest, "ruleAudit": outcome,
    }, indent=2))
    if outcome != "expected-rule-observed":
        raise SystemExit(1)


if __name__ == "__main__":
    main()
