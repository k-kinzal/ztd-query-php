"""Group the fixed grammar inventory by syntactic statement entry alternatives."""

import re
from collections import deque

SYMBOL = re.compile(r"[A-Za-z_][A-Za-z_0-9]*")


def dependencies(signature, rules, entry):
    return {word for word in SYMBOL.findall(signature.partition(":")[2]) if word in rules and word != entry}


def walk_roots(graph, operands):
    """Walk each statement alternative independently."""
    result = dict.fromkeys(graph, 0)
    for number, seed in enumerate(operands):
        pending = list(seed)
        visited = set()
        while pending:
            rule = pending.pop()
            if rule in visited:
                continue
            visited.add(rule)
            result[rule] |= 1 << number
            pending.extend(graph[rule] - visited)
    return result


def propagate(graph, operands):
    """Check the same relation by propagating all root sets to a fixed point."""
    owners = dict.fromkeys(graph, 0)
    for number, seed in enumerate(operands):
        for rule in seed:
            owners[rule] |= 1 << number
    pending = deque(rule for rule, flags in owners.items() if flags)
    while pending:
        rule = pending.popleft()
        for dependency in graph[rule]:
            merged = owners[dependency] | owners[rule]
            if merged != owners[dependency]:
                owners[dependency] = merged
                pending.append(dependency)
    return owners


def statement_contexts(rules, related, entry):
    if entry not in rules or not rules[entry]:
        raise ValueError(f"The required statement entry is absent: {entry}")
    roots = rules[entry]
    seeds = [dependencies(signature, rules, entry) for signature in roots]
    graph = {rule: set().union(*(dependencies(signature, rules, entry) for signature in alternatives))
             for rule, alternatives in rules.items() if rule != entry}
    owners = walk_roots(graph, seeds)
    if owners != propagate(graph, seeds):
        raise ValueError(f"The computed statement contexts disagree: {entry}")
    owners[entry] = (1 << len(roots)) - 1
    grouped = {}
    for rule, mask in sorted(owners.items()):
        grouped.setdefault(mask, []).append(rule)
    groups = []
    for mask, members in sorted(grouped.items()):
        alternatives = [signature for rule in members for signature in rules[rule]]
        groups.append({
            "rootAlternatives": [number for number in range(len(roots)) if mask & (1 << number)],
            "rules": members,
            "alternatives": len(alternatives),
            "alternativesWithRelatedDomain": sum(signature in related for signature in alternatives),
        })
    total = sum(len(alternatives) for alternatives in rules.values())
    if sum(group["alternatives"] for group in groups) != total:
        raise ValueError(f"The statement context groups omit an alternative: {entry}")
    return {
        "entry": entry,
        "rootAlternatives": roots,
        "groups": groups,
        "alternativesWithRelatedDomain": sum(group["alternativesWithRelatedDomain"] for group in groups),
        "alternativesWithoutEntryContext": sum(group["alternatives"] for group in groups if not group["rootAlternatives"]),
        "interpretation": "Groups share syntactic reachability from the listed entry alternatives. Nested re-entry uses its own statement context. Every rule remains in the required inventory.",
    }
