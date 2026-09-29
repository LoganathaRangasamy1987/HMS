package com.caredesk.api.doctor;

import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface DoctorProfileRepository extends MongoRepository<DoctorProfile, String> {
	List<DoctorProfile> findByHospitalIdAndBranchIdAndStatusOrderByName(String hospitalId, String branchId, String status);
	Optional<DoctorProfile> findByIdAndHospitalIdAndBranchIdAndStatus(String id, String hospitalId, String branchId, String status);
}
