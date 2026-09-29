package com.caredesk.api.doctor;

import java.util.List;

import org.bson.types.Decimal128;
import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("doctorProfiles")
public record DoctorProfile(@Id String id, String hospitalId, String branchId, String userId, String name,
		String registrationNumber, String qualification, String specialization, Decimal128 consultationFee,
		String status, List<AvailabilityWindow> weeklyAvailability) {
}
