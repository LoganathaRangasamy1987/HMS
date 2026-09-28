package com.caredesk.api.audit;

import java.util.List;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;

@RestController
@RequestMapping("/api/v1/audit-events")
public class AuditController {
	private final TenantContextService tenants;
	private final AuditEventRepository events;

	public AuditController(TenantContextService tenants, AuditEventRepository events) {
		this.tenants = tenants;
		this.events = events;
	}

	@GetMapping
	public List<AuditEvent> index(Authentication authentication, HttpSession session) {
		TenantSession tenant = tenants.current(authentication, session);
		if (!tenant.permissions().contains("AUDIT.VIEW")) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
		return events.findTop100ByHospitalIdOrderByOccurredAtDesc(tenant.hospitalId());
	}
}
