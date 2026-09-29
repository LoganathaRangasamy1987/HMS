package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.util.List;
import java.util.UUID;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.audit.AuditService;
import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMax;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

@RestController
@RequestMapping("/api/v1/services")
public class ServiceCatalogController {
	private final ServiceItemRepository services;
	private final TenantContextService tenants;
	private final AuditService audit;

	public ServiceCatalogController(ServiceItemRepository services, TenantContextService tenants, AuditService audit) {
		this.services = services;
		this.tenants = tenants;
		this.audit = audit;
	}

	@GetMapping
	public List<ServiceItem> index(Authentication authentication, HttpSession session) {
		TenantSession tenant = tenant(authentication, session, "SERVICE.VIEW");
		return services.findByHospitalIdAndStatusOrderByName(tenant.hospitalId(), "active");
	}

	@PostMapping
	@ResponseStatus(HttpStatus.CREATED)
	public ServiceItem store(Authentication authentication, HttpSession session, @Valid @RequestBody ServiceRequest request) {
		TenantSession tenant = tenant(authentication, session, "SERVICE.MANAGE");
		ServiceItem item = services.save(new ServiceItem(UUID.randomUUID().toString(), tenant.hospitalId(),
			request.code().trim().toUpperCase(), request.name().trim(), request.category().trim(), request.basePrice(),
			request.discountType(), request.discountValue(), request.taxRatePercent(), "active"));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "service_items", "created",
			java.util.Map.of("serviceItemId", item.id(), "code", item.code()));
		return item;
	}

	private TenantSession tenant(Authentication authentication, HttpSession session, String permission) {
		TenantSession tenant = tenants.current(authentication, session);
		if (!tenant.permissions().contains(permission)) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
		return tenant;
	}

	public record ServiceRequest(@NotBlank @Size(max = 30) String code, @NotBlank @Size(max = 150) String name,
			@NotBlank @Size(max = 80) String category, @NotNull @DecimalMin("0.00") BigDecimal basePrice,
			@NotBlank @Pattern(regexp = "NONE|PERCENTAGE|FIXED") String discountType,
			@NotNull @DecimalMin("0.00") BigDecimal discountValue,
			@NotNull @DecimalMin("0.00") @DecimalMax("100.00") BigDecimal taxRatePercent) {}
}
