package com.caredesk.api.billing;

import java.time.Instant;
import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface FinancialAdjustmentRepository extends MongoRepository<FinancialAdjustment, String> {
	List<FinancialAdjustment> findByInvoiceIdOrderByRecordedAt(String invoiceId);
	Optional<FinancialAdjustment> findByHospitalIdAndRequestKey(String hospitalId, String requestKey);
	List<FinancialAdjustment> findByHospitalIdAndBranchIdAndRecordedAtBetween(String hospitalId, String branchId,
		Instant start, Instant end);
}
