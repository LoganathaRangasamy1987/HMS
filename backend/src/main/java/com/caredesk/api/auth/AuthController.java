package com.caredesk.api.auth;

import java.time.Instant;
import java.util.Map;

import org.springframework.http.HttpStatus;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContext;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.context.SecurityContextRepository;
import org.springframework.security.web.csrf.CsrfToken;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import com.caredesk.api.audit.AuditService;
import com.caredesk.api.identity.UserAccount;
import com.caredesk.api.identity.UserAccountRepository;
import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

@RestController
@RequestMapping("/api/v1/auth")
public class AuthController {
	private final AuthenticationManager authenticationManager;
	private final SecurityContextRepository securityContexts = new HttpSessionSecurityContextRepository();
	private final TenantContextService tenants;
	private final UserAccountRepository users;
	private final AuditService audit;

	public AuthController(AuthenticationManager authenticationManager, TenantContextService tenants,
			UserAccountRepository users, AuditService audit) {
		this.authenticationManager = authenticationManager;
		this.tenants = tenants;
		this.users = users;
		this.audit = audit;
	}

	@GetMapping("/csrf")
	public Map<String, String> csrf(CsrfToken token) {
		return Map.of("headerName", token.getHeaderName(), "token", token.getToken());
	}

	@PostMapping("/login")
	public TenantSession login(@Valid @RequestBody LoginRequest credentials, HttpServletRequest request,
			HttpServletResponse response) {
		Authentication authentication = authenticationManager.authenticate(
			UsernamePasswordAuthenticationToken.unauthenticated(credentials.email().trim().toLowerCase(), credentials.password()));
		request.getSession();
		request.changeSessionId();
		SecurityContext context = SecurityContextHolder.createEmptyContext();
		context.setAuthentication(authentication);
		SecurityContextHolder.setContext(context);
		securityContexts.saveContext(context, request, response);
		TenantSession tenant = tenants.current(authentication, request.getSession());
		UserAccount user = users.findById(tenant.userId()).orElseThrow();
		users.save(user.withLastLoginAt(Instant.now()));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "authentication", "signed_in", Map.of());
		return tenant;
	}

	@PostMapping("/logout")
	@ResponseStatus(HttpStatus.NO_CONTENT)
	public void logout(Authentication authentication, HttpServletRequest request) {
		if (authentication != null) {
			TenantSession tenant = tenants.current(authentication, request.getSession());
			audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "authentication", "signed_out", Map.of());
		}
		request.getSession().invalidate();
		SecurityContextHolder.clearContext();
	}

	public record LoginRequest(@NotBlank @Email @Size(max = 150) String email,
			@NotBlank @Size(max = 128) String password) {
	}
}
