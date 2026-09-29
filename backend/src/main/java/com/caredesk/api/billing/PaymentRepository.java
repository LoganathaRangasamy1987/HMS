package com.caredesk.api.billing;

import java.time.Instant;
import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface PaymentRepository extends MongoRepository<Payment, String> {
	List<Payment> findByInvoiceIdOrderByReceivedAt(String invoiceId);
	Optional<Payment> findByHospitalIdAndIdempotencyKey(String hospitalId, String idempotencyKey);
	Optional<Payment> findByIdAndHospitalIdAndBranchIdAndInvoiceId(String id, String hospitalId, String branchId, String invoiceId);
	List<Payment> findByHospitalIdAndBranchIdAndReceivedAtBetween(String hospitalId, String branchId, Instant start,
		Instant end);
}
