package com.caredesk.api.doctor;

import java.time.LocalDate;
import java.util.List;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;

@RestController
@RequestMapping("/api/v1/doctors")
public class DoctorController {
	private final DoctorProfileRepository doctors;
	private final DoctorAvailabilityService availability;
	private final TenantContextService tenants;

	public DoctorController(DoctorProfileRepository doctors, DoctorAvailabilityService availability,
			TenantContextService tenants) {
		this.doctors = doctors;
		this.availability = availability;
		this.tenants = tenants;
	}

	@GetMapping
	public List<DoctorProfile> index(Authentication authentication, HttpSession session) {
		TenantSession tenant = tenant(authentication, session, "DOCTOR.VIEW");
		return doctors.findByHospitalIdAndBranchIdAndStatusOrderByName(tenant.hospitalId(), tenant.branchId(), "active");
	}

	@GetMapping("/{doctorId}/availability")
	public List<DoctorAvailabilityService.AvailableDate> availability(Authentication authentication,
			HttpSession session, @PathVariable String doctorId,
			@RequestParam(required = false) LocalDate from, @RequestParam(defaultValue = "30") int days) {
		TenantSession tenant = tenant(authentication, session, "DOCTOR_AVAILABILITY.VIEW");
		return availability.availableDates(tenant, doctorId, from, days);
	}

	private TenantSession tenant(Authentication authentication, HttpSession session, String permission) {
		TenantSession tenant = tenants.current(authentication, session);
		if (!tenant.permissions().contains(permission)) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
		return tenant;
	}
}
