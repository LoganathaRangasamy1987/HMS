package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.identity.Branch;
import com.caredesk.api.identity.BranchRepository;
import com.caredesk.api.tenant.TenantContextService;
import com.caredesk.api.tenant.TenantSession;

import jakarta.servlet.http.HttpSession;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotEmpty;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

@RestController
@RequestMapping("/api/v1")
public class BillingController {
	private final InvoiceRepository invoices;
	private final BillingService billing;
	private final TenantContextService tenants;
	private final BranchRepository branches;

	public BillingController(InvoiceRepository invoices, BillingService billing, TenantContextService tenants,
			BranchRepository branches) {
		this.invoices = invoices;
		this.billing = billing;
		this.tenants = tenants;
		this.branches = branches;
	}

	@GetMapping("/invoices")
	public List<Invoice> index(Authentication authentication, HttpSession session) {
		TenantSession tenant = tenant(authentication, session, "INVOICE.MANAGE");
		return invoices.findTop100ByHospitalIdAndBranchIdOrderByCreatedAtDesc(tenant.hospitalId(), tenant.branchId());
	}

	@PostMapping("/invoices")
	@ResponseStatus(HttpStatus.CREATED)
	public Invoice store(Authentication authentication, HttpSession session, @Valid @RequestBody InvoiceRequest request) {
		return billing.createDraft(tenant(authentication, session, "INVOICE.MANAGE"), request);
	}

	@GetMapping("/invoices/{invoiceId}")
	public BillingService.InvoiceView show(Authentication authentication, HttpSession session, @PathVariable String invoiceId) {
		return billing.view(tenant(authentication, session, "INVOICE.MANAGE"), invoiceId);
	}

	@PostMapping("/invoices/{invoiceId}/issue")
	public Invoice issue(Authentication authentication, HttpSession session, @PathVariable String invoiceId) {
		return billing.issue(tenant(authentication, session, "INVOICE.MANAGE"), invoiceId);
	}

	@PostMapping("/invoices/{invoiceId}/payments")
	public ResponseEntity<BillingService.ActionResult<Payment>> payment(Authentication authentication, HttpSession session,
			@PathVariable String invoiceId, @Valid @RequestBody PaymentRequest request) {
		BillingService.ActionResult<Payment> result = billing.collect(tenant(authentication, session, "PAYMENT.MANAGE"), invoiceId, request);
		return ResponseEntity.status(result.replayed() ? HttpStatus.OK : HttpStatus.CREATED).body(result);
	}

	@PostMapping("/invoices/{invoiceId}/adjustments")
	public ResponseEntity<BillingService.ActionResult<FinancialAdjustment>> adjustment(Authentication authentication,
			HttpSession session, @PathVariable String invoiceId, @Valid @RequestBody AdjustmentRequest request) {
		BillingService.ActionResult<FinancialAdjustment> result = billing.adjust(
			tenant(authentication, session, "FINANCIAL_ADJUSTMENT.MANAGE"), invoiceId, request);
		return ResponseEntity.status(result.replayed() ? HttpStatus.OK : HttpStatus.CREATED).body(result);
	}

	@PostMapping("/invoices/{invoiceId}/payments/{paymentId}/refunds")
	public ResponseEntity<BillingService.ActionResult<FinancialAdjustment>> refund(Authentication authentication, HttpSession session,
			@PathVariable String invoiceId, @PathVariable String paymentId, @Valid @RequestBody RefundRequest request) {
		BillingService.ActionResult<FinancialAdjustment> result = billing.refund(
			tenant(authentication, session, "FINANCIAL_ADJUSTMENT.MANAGE"), invoiceId, paymentId, request);
		return ResponseEntity.status(result.replayed() ? HttpStatus.OK : HttpStatus.CREATED).body(result);
	}

	@PostMapping("/invoices/{invoiceId}/void")
	public ResponseEntity<BillingService.ActionResult<FinancialAdjustment>> voidInvoice(Authentication authentication,
			HttpSession session, @PathVariable String invoiceId, @Valid @RequestBody VoidRequest request) {
		BillingService.ActionResult<FinancialAdjustment> result = billing.voidInvoice(
			tenant(authentication, session, "FINANCIAL_ADJUSTMENT.MANAGE"), invoiceId, request);
		return ResponseEntity.status(result.replayed() ? HttpStatus.OK : HttpStatus.CREATED).body(result);
	}

	@GetMapping("/billing/reconciliation")
	public BillingService.Reconciliation reconciliation(Authentication authentication, HttpSession session,
			@RequestParam(required = false) LocalDate date) {
		TenantSession tenant = tenant(authentication, session, "BILLING_RECONCILIATION.VIEW");
		Branch branch = branches.findById(tenant.branchId()).filter(item -> item.hospitalId().equals(tenant.hospitalId()))
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
		ZoneId zone = ZoneId.of(branch.timezone());
		return billing.reconciliation(tenant, date == null ? LocalDate.now(zone) : date, zone);
	}

	private TenantSession tenant(Authentication authentication, HttpSession session, String permission) {
		TenantSession tenant = tenants.current(authentication, session);
		if (!tenant.permissions().contains(permission)) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Permission denied.");
		}
		return tenant;
	}

	public record LineRequest(@NotBlank String serviceItemId, @Min(1) @Max(1000) int quantity) {}
	public record InvoiceRequest(@NotBlank String patientId, String appointmentId, @NotEmpty List<@Valid LineRequest> lines) {}
	public record PaymentRequest(@NotNull @DecimalMin("0.01") BigDecimal amount,
			@NotBlank @Pattern(regexp = "CASH|UPI|CARD|BANK_TRANSFER") String mode, @Size(max = 100) String reference,
			@NotBlank @Pattern(regexp = "[0-9a-fA-F-]{36}") String requestKey) {}
	public record AdjustmentRequest(@NotBlank @Pattern(regexp = "CREDIT|DEBIT") String type,
			@NotNull @DecimalMin("0.01") BigDecimal amount, @NotBlank @Size(max = 500) String reason,
			@NotBlank @Pattern(regexp = "[0-9a-fA-F-]{36}") String requestKey) {}
	public record RefundRequest(@NotNull @DecimalMin("0.01") BigDecimal amount,
			@NotBlank @Size(max = 500) String reason,
			@NotBlank @Pattern(regexp = "[0-9a-fA-F-]{36}") String requestKey) {}
	public record VoidRequest(@NotBlank @Size(max = 500) String reason,
			@NotBlank @Pattern(regexp = "[0-9a-fA-F-]{36}") String requestKey) {}
}
