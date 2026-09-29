package com.caredesk.api.appointment;

import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.Map;
import java.util.UUID;

import org.springframework.dao.DuplicateKeyException;
import org.springframework.data.mongodb.core.FindAndModifyOptions;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.query.Criteria;
import org.springframework.data.mongodb.core.query.Query;
import org.springframework.data.mongodb.core.query.Update;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.audit.AuditService;
import com.caredesk.api.doctor.DoctorAvailabilityService;
import com.caredesk.api.doctor.DoctorProfile;
import com.caredesk.api.patient.Patient;
import com.caredesk.api.patient.PatientRepository;
import com.caredesk.api.tenant.TenantSession;

import jakarta.validation.constraints.FutureOrPresent;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Pattern;

@Service
public class AppointmentService {
	private final AppointmentRepository appointments;
	private final PatientRepository patients;
	private final DoctorAvailabilityService availability;
	private final MongoTemplate mongoTemplate;
	private final AuditService audit;

	public AppointmentService(AppointmentRepository appointments, PatientRepository patients,
			DoctorAvailabilityService availability, MongoTemplate mongoTemplate, AuditService audit) {
		this.appointments = appointments;
		this.patients = patients;
		this.availability = availability;
		this.mongoTemplate = mongoTemplate;
		this.audit = audit;
	}

	@Transactional
	public Appointment book(TenantSession tenant, BookingRequest request) {
		Patient patient = patients.findByIdAndHospitalId(request.patientId(), tenant.hospitalId())
			.filter(item -> "active".equals(item.status()))
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Patient not found."));
		DoctorProfile doctor = availability.doctor(tenant, request.doctorProfileId());
		Appointment existing = appointments.findByHospitalIdAndRequestKey(tenant.hospitalId(), request.requestKey()).orElse(null);
		if (existing != null) {
			if (sameRequest(existing, request)) {
				return existing;
			}
			throw new ResponseStatusException(HttpStatus.CONFLICT, "Request key was already used with different appointment details.");
		}

		DoctorAvailabilityService.Slot slot = availability.availableDates(tenant, doctor.id(), request.appointmentDate(), 1)
			.stream().filter(item -> item.date().equals(request.appointmentDate())).flatMap(item -> item.slots().stream())
			.filter(item -> item.startsAt().equals(request.startsAt()))
			.findFirst().orElseThrow(() -> new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, "Select a valid available time slot."));
		LocalTime endsAt = slot.endsAt();

		String slotId = doctor.id() + ":" + request.appointmentDate() + ":" + request.startsAt();
		try {
			AppointmentSlotLedger ledger = mongoTemplate.findAndModify(
				Query.query(Criteria.where("_id").is(slotId).and("bookedCount").lt(slot.capacity())),
				new Update().setOnInsert("hospitalId", tenant.hospitalId()).setOnInsert("branchId", tenant.branchId())
					.setOnInsert("doctorProfileId", doctor.id()).setOnInsert("date", request.appointmentDate().toString())
					.setOnInsert("startsAt", request.startsAt().toString()).setOnInsert("capacity", slot.capacity())
					.inc("bookedCount", 1), FindAndModifyOptions.options().upsert(true).returnNew(true), AppointmentSlotLedger.class);
			if (ledger == null || ledger.bookedCount() > ledger.capacity()) {
				throw slotFull();
			}
		} catch (DuplicateKeyException exception) {
			throw slotFull();
		}

		SequenceCounter token = mongoTemplate.findAndModify(
			Query.query(Criteria.where("_id").is("appointment:" + doctor.id() + ":" + request.appointmentDate())),
			new Update().inc("value", 1), FindAndModifyOptions.options().upsert(true).returnNew(true), SequenceCounter.class);
		Appointment appointment = appointments.save(new Appointment(UUID.randomUUID().toString(), tenant.hospitalId(),
			tenant.branchId(), patient.id(), patient.displayName(), patient.uhid(), doctor.id(), doctor.name(),
			request.appointmentDate(), request.startsAt(), endsAt, token.value(), request.type(), "BOOKED",
			normalized(request.reason()), request.requestKey(), tenant.userId(), Instant.now()));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "appointments", "booked",
			Map.of("appointmentId", appointment.id(), "tokenNumber", Long.toString(appointment.tokenNumber())));
		return appointment;
	}

	@Transactional
	public Appointment transition(TenantSession tenant, String appointmentId, String status) {
		Appointment appointment = appointments.findByIdAndHospitalIdAndBranchId(appointmentId, tenant.hospitalId(), tenant.branchId())
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
		if (!allowed(appointment.status(), status)) {
			throw new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, "Invalid appointment status transition.");
		}
		Appointment updated = appointments.save(appointment.withStatus(status));
		if ("CANCELLED".equals(status)) {
			String slotId = appointment.doctorProfileId() + ":" + appointment.appointmentDate() + ":" + appointment.startsAt();
			mongoTemplate.updateFirst(Query.query(Criteria.where("_id").is(slotId).and("bookedCount").gt(0)),
				new Update().inc("bookedCount", -1), AppointmentSlotLedger.class);
		}
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "appointments", "status_changed",
			Map.of("appointmentId", updated.id(), "status", status));
		return updated;
	}

	private static boolean sameRequest(Appointment appointment, BookingRequest request) {
		return appointment.patientId().equals(request.patientId()) && appointment.doctorProfileId().equals(request.doctorProfileId())
			&& appointment.appointmentDate().equals(request.appointmentDate()) && appointment.startsAt().equals(request.startsAt())
			&& appointment.type().equals(request.type());
	}

	private static boolean allowed(String from, String to) {
		return ("BOOKED".equals(from) && ("CHECKED_IN".equals(to) || "CANCELLED".equals(to)))
			|| ("CHECKED_IN".equals(from) && ("WAITING".equals(to) || "CANCELLED".equals(to)));
	}

	private static ResponseStatusException slotFull() {
		return new ResponseStatusException(HttpStatus.CONFLICT, "This appointment slot is full.");
	}

	private static String normalized(String value) {
		return value == null || value.isBlank() ? null : value.trim();
	}

	public record BookingRequest(@NotBlank String patientId, @NotBlank String doctorProfileId,
			@NotNull @FutureOrPresent LocalDate appointmentDate, @NotNull LocalTime startsAt,
			@NotBlank @Pattern(regexp = "NEW|FOLLOWUP|WALK_IN|ONLINE") String type, String reason,
			@NotBlank @Pattern(regexp = "[0-9a-fA-F-]{36}") String requestKey) {}
}
