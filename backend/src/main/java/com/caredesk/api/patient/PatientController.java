package com.caredesk.api.patient;

import java.time.Instant;
import java.time.LocalDate;
import java.time.Year;
import java.util.List;
import java.util.UUID;

import org.springframework.data.mongodb.core.FindAndModifyOptions;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.query.Criteria;
import org.springframework.data.mongodb.core.query.Query;
import org.springframework.data.mongodb.core.query.Update;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.appointment.SequenceCounter;
import com.caredesk.api.audit.AuditService;
import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Past;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

@RestController
@RequestMapping("/api/v1/patients")
public class PatientController {
	private final PatientRepository patients;
	private final TenantContextService tenants;
	private final MongoTemplate mongoTemplate;
	private final AuditService audit;

	public PatientController(PatientRepository patients, TenantContextService tenants, MongoTemplate mongoTemplate,
			AuditService audit) {
		this.patients = patients;
		this.tenants = tenants;
		this.mongoTemplate = mongoTemplate;
		this.audit = audit;
	}

	@GetMapping
	public List<Patient> index(Authentication authentication, HttpSession session) {
		TenantSession tenant = tenants.current(authentication, session);
		require(tenant, "PATIENT.VIEW");
		return patients.findTop100ByHospitalIdAndStatusOrderByCreatedAtDesc(tenant.hospitalId(), "active");
	}

	@PostMapping
	@ResponseStatus(HttpStatus.CREATED)
	public Patient store(Authentication authentication, HttpSession session, @Valid @RequestBody PatientRequest request) {
		TenantSession tenant = tenants.current(authentication, session);
		require(tenant, "PATIENT.MANAGE");
		SequenceCounter counter = mongoTemplate.findAndModify(
			Query.query(Criteria.where("_id").is("patient:" + tenant.hospitalId() + ":" + Year.now().getValue())),
			new Update().inc("value", 1), FindAndModifyOptions.options().upsert(true).returnNew(true), SequenceCounter.class);
		String uhid = "UHID-%d-%05d".formatted(Year.now().getValue(), counter.value());
		Patient patient = patients.save(new Patient(UUID.randomUUID().toString(), tenant.hospitalId(), tenant.branchId(),
			uhid, request.firstName().trim(), normalized(request.lastName()), request.dateOfBirth(), request.gender(),
			request.mobile(), normalized(request.email()), normalized(request.address()), "active", tenant.userId(), Instant.now()));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "patients", "created",
			java.util.Map.of("patientId", patient.id(), "uhid", patient.uhid()));
		return patient;
	}

	private static void require(TenantSession tenant, String permission) {
		if (!tenant.permissions().contains(permission)) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
	}

	private static String normalized(String value) {
		return value == null || value.isBlank() ? null : value.trim();
	}

	public record PatientRequest(@NotBlank @Size(max = 100) String firstName, @Size(max = 100) String lastName,
			@Past LocalDate dateOfBirth, @NotBlank @Pattern(regexp = "MALE|FEMALE|OTHER|UNKNOWN") String gender,
			@NotBlank @Pattern(regexp = "[0-9+ -]{7,20}") String mobile, @Email @Size(max = 150) String email,
			@Size(max = 500) String address) {
	}
}
