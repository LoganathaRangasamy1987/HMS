package com.caredesk.api.billing;

import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface ServiceItemRepository extends MongoRepository<ServiceItem, String> {
	List<ServiceItem> findByHospitalIdAndStatusOrderByName(String hospitalId, String status);
	Optional<ServiceItem> findByIdAndHospitalIdAndStatus(String id, String hospitalId, String status);
}
