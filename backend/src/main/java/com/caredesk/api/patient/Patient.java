package com.caredesk.api.patient;

import java.time.Instant;
import java.time.LocalDate;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("patients")
public record Patient(@Id String id, String hospitalId, String registrationBranchId, String uhid,
		String firstName, String lastName, LocalDate dateOfBirth, String gender, String mobile, String email,
		String address, String status, String registeredBy, Instant createdAt) {
	public String displayName() {
		return (firstName + " " + (lastName == null ? "" : lastName)).trim();
	}
}
