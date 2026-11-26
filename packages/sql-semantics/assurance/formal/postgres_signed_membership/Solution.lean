import MembershipSpec

namespace SqlMembership

theorem positive (subject : Option Int) (alternatives : List (Option Int)) :
    inList subject alternatives = anyEqual subject alternatives := by
  induction alternatives with
  | nil => rfl
  | cons slot remaining inductionHypothesis =>
      change truthOr (exactEqual subject slot) (inList subject remaining) =
        anyEqual subject (slot :: remaining)
      rw [inductionHypothesis]
      simp only [anyEqual]
      cases exactEqual subject slot <;> cases anyEqual subject remaining <;> rfl

private theorem deMorgan (left right : Truth) :
    truthAnd (negate left) (negate right) = negate (truthOr left right) := by
  cases left <;> cases right <;> rfl

theorem negative (subject : Option Int) (alternatives : List (Option Int)) :
    notInList subject alternatives = negate (anyEqual subject alternatives) := by
  induction alternatives with
  | nil => rfl
  | cons slot remaining inductionHypothesis =>
      change truthAnd (negate (exactEqual subject slot)) (notInList subject remaining) =
        negate (anyEqual subject (slot :: remaining))
      rw [inductionHypothesis, deMorgan, ← positive subject (slot :: remaining),
          ← positive subject remaining]
      rfl

#print axioms positive
#print axioms negative

end SqlMembership
