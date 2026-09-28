package com.caredesk.api.identity;

import java.time.Instant;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("users")
public record UserAccount(@Id String id, String hospitalId, String email, String name, String passwordHash,
		String status, Instant lastLoginAt) {

	public UserAccount withLastLoginAt(Instant value) {
		return new UserAccount(id, hospitalId, email, name, passwordHash, status, value);
	}
}
