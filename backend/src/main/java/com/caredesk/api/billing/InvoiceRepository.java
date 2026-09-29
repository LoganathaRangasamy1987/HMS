package com.caredesk.api.billing;

import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface InvoiceRepository extends MongoRepository<Invoice, String> {
	List<Invoice> findTop100ByHospitalIdAndBranchIdOrderByCreatedAtDesc(String hospitalId, String branchId);
	Optional<Invoice> findByIdAndHospitalIdAndBranchId(String id, String hospitalId, String branchId);
	boolean existsByAppointmentId(String appointmentId);
}
