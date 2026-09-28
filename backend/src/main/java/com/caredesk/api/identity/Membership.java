package com.caredesk.api.identity;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("memberships")
public record Membership(@Id String id, String hospitalId, String branchId, String userId, String roleId,
		String status) {
}
