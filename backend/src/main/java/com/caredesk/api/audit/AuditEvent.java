package com.caredesk.api.audit;

import java.time.Instant;
import java.util.Map;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("auditEvents")
public record AuditEvent(@Id String id, String hospitalId, String branchId, String userId, String module,
		String action, Instant occurredAt, Map<String, String> metadata) {
}
