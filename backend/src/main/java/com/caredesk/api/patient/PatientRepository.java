package com.caredesk.api.patient;

import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface PatientRepository extends MongoRepository<Patient, String> {
	List<Patient> findTop100ByHospitalIdAndStatusOrderByCreatedAtDesc(String hospitalId, String status);
	Optional<Patient> findByIdAndHospitalId(String id, String hospitalId);
}
