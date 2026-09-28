package com.caredesk.api.audit;

import java.time.Clock;
import java.time.Instant;
import java.util.Map;
import java.util.UUID;

import org.springframework.stereotype.Service;

@Service
public class AuditService {
	private final AuditEventRepository repository;
	private final Clock clock = Clock.systemUTC();

	public AuditService(AuditEventRepository repository) {
		this.repository = repository;
	}

	public void record(String hospitalId, String branchId, String userId, String module, String action,
			Map<String, String> metadata) {
		repository.save(new AuditEvent(UUID.randomUUID().toString(), hospitalId, branchId, userId, module,
			action, Instant.now(clock), Map.copyOf(metadata)));
	}
}
