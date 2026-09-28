package com.caredesk.api.audit;

import java.util.List;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface AuditEventRepository extends MongoRepository<AuditEvent, String> {
	List<AuditEvent> findTop100ByHospitalIdOrderByOccurredAtDesc(String hospitalId);
}
