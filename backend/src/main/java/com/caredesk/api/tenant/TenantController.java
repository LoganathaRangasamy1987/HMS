package com.caredesk.api.tenant;

import java.util.Map;

import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import com.caredesk.api.audit.AuditService;

import jakarta.servlet.http.HttpSession;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;

@RestController
@RequestMapping("/api/v1")
public class TenantController {
	private final TenantContextService tenants;
	private final AuditService audit;

	public TenantController(TenantContextService tenants, AuditService audit) {
		this.tenants = tenants;
		this.audit = audit;
	}

	@GetMapping("/me")
	public TenantSession me(Authentication authentication, HttpSession session) {
		return tenants.current(authentication, session);
	}

	@PostMapping("/context")
	public TenantSession switchContext(Authentication authentication, HttpSession session,
			@Valid @RequestBody ContextRequest request) {
		TenantSession tenant = tenants.switchMembership(authentication, session, request.membershipId());
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "access", "branch_selected",
			Map.of("membershipId", tenant.membershipId()));
		return tenant;
	}

	public record ContextRequest(@NotBlank String membershipId) {
	}
}
