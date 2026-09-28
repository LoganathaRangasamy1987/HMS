package com.caredesk.api.tenant;

import java.util.List;
import java.util.Set;

public record TenantSession(String userId, String email, String name, String hospitalId, String hospitalName,
		String membershipId, String branchId, String branchName, String role, Set<String> permissions,
		List<MembershipOption> memberships) {

	public record MembershipOption(String id, String branchId, String branchName, String role) {
	}
}
