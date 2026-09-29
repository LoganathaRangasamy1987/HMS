package com.caredesk.api.appointment;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("appointmentSlotLedgers")
public record AppointmentSlotLedger(@Id String id, String hospitalId, String branchId, String doctorProfileId,
		String date, String startsAt, int capacity, int bookedCount) {
}
