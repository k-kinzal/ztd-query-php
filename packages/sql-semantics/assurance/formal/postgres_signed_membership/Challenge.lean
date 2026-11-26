import MembershipSpec

namespace SqlMembership

theorem positive (subject : Option Int) (alternatives : List (Option Int)) :
    inList subject alternatives = anyEqual subject alternatives := by
  sorry

theorem negative (subject : Option Int) (alternatives : List (Option Int)) :
    notInList subject alternatives = negate (anyEqual subject alternatives) := by
  sorry

end SqlMembership
