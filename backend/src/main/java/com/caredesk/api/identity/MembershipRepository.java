package com.caredesk.api.identity;

import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface MembershipRepository extends MongoRepository<Membership, String> {
	List<Membership> findByUserIdAndStatusOrderById(String userId, String status);
	Optional<Membership> findByIdAndUserIdAndStatus(String id, String userId, String status);
}
