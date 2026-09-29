package com.caredesk.api.appointment;

import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("appointments")
public record Appointment(@Id String id, String hospitalId, String branchId, String patientId,
		String patientName, String patientUhid, String doctorProfileId, String doctorName, LocalDate appointmentDate,
		LocalTime startsAt, LocalTime endsAt, long tokenNumber, String type, String status, String reason,
		String requestKey, String createdBy, Instant createdAt) {
	public Appointment withStatus(String value) {
		return new Appointment(id, hospitalId, branchId, patientId, patientName, patientUhid, doctorProfileId,
			doctorName, appointmentDate, startsAt, endsAt, tokenNumber, type, value, reason, requestKey, createdBy, createdAt);
	}
}
