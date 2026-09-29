package com.caredesk.api.appointment;

import java.time.LocalDate;
import java.util.List;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PatchMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Pattern;

@RestController
@RequestMapping("/api/v1/appointments")
public class AppointmentController {
	private final AppointmentRepository appointments;
	private final AppointmentService service;
	private final TenantContextService tenants;

	public AppointmentController(AppointmentRepository appointments, AppointmentService service,
			TenantContextService tenants) {
		this.appointments = appointments;
		this.service = service;
		this.tenants = tenants;
	}

	@GetMapping
	public List<Appointment> index(Authentication authentication, HttpSession session,
			@RequestParam(required = false) LocalDate date) {
		TenantSession tenant = tenant(authentication, session, "APPOINTMENT.VIEW");
		return appointments.findByHospitalIdAndBranchIdAndAppointmentDateOrderByTokenNumber(tenant.hospitalId(),
			tenant.branchId(), date == null ? LocalDate.now() : date);
	}

	@PostMapping
	@ResponseStatus(HttpStatus.CREATED)
	public Appointment store(Authentication authentication, HttpSession session,
			@Valid @RequestBody AppointmentService.BookingRequest request) {
		return service.book(tenant(authentication, session, "APPOINTMENT.MANAGE"), request);
	}

	@PatchMapping("/{appointmentId}/status")
	public Appointment status(Authentication authentication, HttpSession session, @PathVariable String appointmentId,
			@Valid @RequestBody StatusRequest request) {
		return service.transition(tenant(authentication, session, "APPOINTMENT.MANAGE"), appointmentId, request.status());
	}

	private TenantSession tenant(Authentication authentication, HttpSession session, String permission) {
		TenantSession tenant = tenants.current(authentication, session);
		if (!tenant.permissions().contains(permission)) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
		return tenant;
	}

	public record StatusRequest(@Pattern(regexp = "CHECKED_IN|WAITING|CANCELLED") String status) {}
}
