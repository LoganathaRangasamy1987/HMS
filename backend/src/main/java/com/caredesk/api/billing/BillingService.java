package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.time.Instant;
import java.time.LocalDate;
import java.time.Year;
import java.time.ZoneId;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;

import org.springframework.data.mongodb.core.FindAndModifyOptions;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.query.Criteria;
import org.springframework.data.mongodb.core.query.Query;
import org.springframework.data.mongodb.core.query.Update;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.appointment.Appointment;
import com.caredesk.api.appointment.AppointmentRepository;
import com.caredesk.api.appointment.SequenceCounter;
import com.caredesk.api.audit.AuditService;
import com.caredesk.api.patient.Patient;
import com.caredesk.api.patient.PatientRepository;
import com.caredesk.api.tenant.TenantSession;

@Service
public class BillingService {
	private static final BigDecimal HUNDRED = new BigDecimal("100");
	private final InvoiceRepository invoices;
	private final ServiceItemRepository services;
	private final PatientRepository patients;
	private final AppointmentRepository appointments;
	private final PaymentRepository payments;
	private final FinancialAdjustmentRepository adjustments;
	private final MongoTemplate mongoTemplate;
	private final AuditService audit;

	public BillingService(InvoiceRepository invoices, ServiceItemRepository services, PatientRepository patients,
			AppointmentRepository appointments, PaymentRepository payments,
			FinancialAdjustmentRepository adjustments, MongoTemplate mongoTemplate, AuditService audit) {
		this.invoices = invoices;
		this.services = services;
		this.patients = patients;
		this.appointments = appointments;
		this.payments = payments;
		this.adjustments = adjustments;
		this.mongoTemplate = mongoTemplate;
		this.audit = audit;
	}

	@Transactional
	public Invoice createDraft(TenantSession tenant, BillingController.InvoiceRequest request) {
		Patient patient = patients.findByIdAndHospitalId(request.patientId(), tenant.hospitalId())
			.filter(item -> "active".equals(item.status()))
			.orElseThrow(() -> notFound("Patient not found."));
		Appointment appointment = null;
		if (request.appointmentId() != null && !request.appointmentId().isBlank()) {
			appointment = appointments.findByIdAndHospitalIdAndBranchId(request.appointmentId(), tenant.hospitalId(), tenant.branchId())
				.filter(item -> item.patientId().equals(patient.id()) && !List.of("CANCELLED", "NO_SHOW").contains(item.status()))
				.orElseThrow(() -> invalid("Select an active appointment for this patient."));
			if (invoices.existsByAppointmentId(appointment.id())) {
				throw conflict("This appointment already has an invoice.");
			}
		}

		List<InvoiceLine> lines = request.lines().stream().map(entry -> line(tenant, entry)).toList();
		BigDecimal subtotal = sum(lines, InvoiceLine::subtotal);
		BigDecimal discount = sum(lines, InvoiceLine::discountAmount);
		BigDecimal tax = sum(lines, InvoiceLine::taxAmount);
		BigDecimal total = sum(lines, InvoiceLine::total);
		Invoice invoice = invoices.save(new Invoice(UUID.randomUUID().toString(), tenant.hospitalId(), tenant.branchId(),
			patient.id(), patient.displayName(), patient.uhid(), appointment == null ? null : appointment.id(),
			"DRAFT-" + UUID.randomUUID(), "INR",
			"DRAFT", lines, subtotal, discount, tax, total, tenant.userId(), Instant.now(), null, null));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "invoices", "created",
			Map.of("invoiceId", invoice.id(), "total", money(invoice.total())));
		return invoice;
	}

	@Transactional
	public Invoice issue(TenantSession tenant, String invoiceId) {
		Invoice invoice = invoice(tenant, invoiceId);
		if (!"DRAFT".equals(invoice.status()) || invoice.lines().isEmpty()) {
			throw invalid("Only a draft with line items can be issued.");
		}
		long next = next("invoice:" + tenant.hospitalId() + ":" + Year.now().getValue());
		Invoice issued = invoices.save(invoice.issued("INV-%d-%06d".formatted(Year.now().getValue(), next),
			tenant.userId(), Instant.now()));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "invoices", "issued",
			Map.of("invoiceId", issued.id(), "number", issued.number()));
		return issued;
	}

	@Transactional
	public ActionResult<Payment> collect(TenantSession tenant, String invoiceId, BillingController.PaymentRequest request) {
		Invoice invoice = invoice(tenant, invoiceId);
		Payment existing = payments.findByHospitalIdAndIdempotencyKey(tenant.hospitalId(), request.requestKey()).orElse(null);
		if (existing != null) {
			if (existing.invoiceId().equals(invoice.id()) && existing.amount().compareTo(request.amount()) == 0
					&& existing.mode().equals(request.mode()) && java.util.Objects.equals(existing.reference(), normalized(request.reference()))) {
				return new ActionResult<>(existing, invoice, summary(invoice), true);
			}
			throw conflict("This request key was already used for a different payment.");
		}
		if (!List.of("ISSUED", "PARTIALLY_PAID").contains(invoice.status())) {
			throw invalid("Payments require an issued invoice with a balance.");
		}
		InvoiceSummary before = summary(invoice);
		if (request.amount().compareTo(BigDecimal.ZERO) <= 0 || request.amount().compareTo(before.balance()) > 0) {
			throw invalid("Payment cannot exceed the remaining balance.");
		}
		if (!"CASH".equals(request.mode()) && normalized(request.reference()) == null) {
			throw invalid("A reference is required for non-cash payments.");
		}
		long receipt = next("receipt:" + tenant.hospitalId() + ":" + Year.now().getValue());
		Payment payment = payments.save(new Payment(UUID.randomUUID().toString(), tenant.hospitalId(), tenant.branchId(),
			invoice.id(), "RCP-%d-%06d".formatted(Year.now().getValue(), receipt), scale(request.amount()), request.mode(),
			normalized(request.reference()), request.requestKey(), tenant.userId(), Instant.now()));
		InvoiceSummary after = summary(invoice);
		Invoice updated = invoices.save(invoice.withStatus(after.balance().signum() == 0 ? "PAID" : "PARTIALLY_PAID"));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "payments", "recorded",
			Map.of("paymentId", payment.id(), "invoiceId", invoice.id(), "receiptNumber", payment.receiptNumber()));
		return new ActionResult<>(payment, updated, summary(updated), false);
	}

	@Transactional
	public ActionResult<FinancialAdjustment> adjust(TenantSession tenant, String invoiceId,
			BillingController.AdjustmentRequest request) {
		return recordAdjustment(tenant, invoice(tenant, invoiceId), null, request.type(), request.amount(),
			request.reason(), request.requestKey());
	}

	@Transactional
	public ActionResult<FinancialAdjustment> refund(TenantSession tenant, String invoiceId, String paymentId,
			BillingController.RefundRequest request) {
		Invoice invoice = invoice(tenant, invoiceId);
		Payment payment = payments.findByIdAndHospitalIdAndBranchIdAndInvoiceId(paymentId, tenant.hospitalId(),
			tenant.branchId(), invoice.id()).orElseThrow(() -> notFound("Payment not found."));
		if (adjustments.findByHospitalIdAndRequestKey(tenant.hospitalId(), request.requestKey()).isPresent()) {
			return recordAdjustment(tenant, invoice, payment, "REFUND", request.amount(), request.reason(), request.requestKey());
		}
		BigDecimal refunded = adjustments.findByInvoiceIdOrderByRecordedAt(invoice.id()).stream()
			.filter(item -> "REFUND".equals(item.type()) && payment.id().equals(item.paymentId()))
			.map(FinancialAdjustment::amount).reduce(BigDecimal.ZERO, BigDecimal::add);
		if (request.amount().compareTo(payment.amount().subtract(refunded)) > 0) {
			throw invalid("Refund cannot exceed the unrefunded payment amount.");
		}
		return recordAdjustment(tenant, invoice, payment, "REFUND", request.amount(), request.reason(), request.requestKey());
	}

	@Transactional
	public ActionResult<FinancialAdjustment> voidInvoice(TenantSession tenant, String invoiceId,
			BillingController.VoidRequest request) {
		Invoice invoice = invoice(tenant, invoiceId);
		return recordAdjustment(tenant, invoice, null, "VOID", invoice.total(), request.reason(), request.requestKey());
	}

	public InvoiceView view(TenantSession tenant, String invoiceId) {
		Invoice invoice = invoice(tenant, invoiceId);
		return new InvoiceView(invoice, payments.findByInvoiceIdOrderByReceivedAt(invoice.id()),
			adjustments.findByInvoiceIdOrderByRecordedAt(invoice.id()), summary(invoice));
	}

	public Reconciliation reconciliation(TenantSession tenant, LocalDate date, ZoneId zone) {
		Instant start = date.atStartOfDay(zone).toInstant();
		Instant end = date.plusDays(1).atStartOfDay(zone).toInstant();
		List<Payment> dayPayments = payments.findByHospitalIdAndBranchIdAndReceivedAtBetween(
			tenant.hospitalId(), tenant.branchId(), start, end);
		List<FinancialAdjustment> dayAdjustments = adjustments
			.findByHospitalIdAndBranchIdAndRecordedAtBetween(tenant.hospitalId(), tenant.branchId(), start, end);
		BigDecimal gross = dayPayments.stream().map(Payment::amount).reduce(BigDecimal.ZERO, BigDecimal::add);
		BigDecimal refunds = dayAdjustments.stream().filter(item -> "REFUND".equals(item.type()))
			.map(FinancialAdjustment::amount).reduce(BigDecimal.ZERO, BigDecimal::add);
		Map<String, ModeTotal> modes = new LinkedHashMap<>();
		for (Payment payment : dayPayments) {
			ModeTotal current = modes.getOrDefault(payment.mode(), new ModeTotal(0, BigDecimal.ZERO));
			modes.put(payment.mode(), new ModeTotal(current.count() + 1, scale(current.total().add(payment.amount()))));
		}
		return new Reconciliation(date, zone.getId(), scale(gross), scale(refunds), scale(gross.subtract(refunds)),
			dayPayments.size(), (int) dayAdjustments.stream().filter(item -> "REFUND".equals(item.type())).count(), modes,
			dayPayments, dayAdjustments.stream().filter(item -> "REFUND".equals(item.type())).toList());
	}

	public InvoiceSummary summary(Invoice invoice) {
		BigDecimal gross = payments.findByInvoiceIdOrderByReceivedAt(invoice.id()).stream().map(Payment::amount)
			.reduce(BigDecimal.ZERO, BigDecimal::add);
		List<FinancialAdjustment> entries = adjustments.findByInvoiceIdOrderByRecordedAt(invoice.id());
		BigDecimal refunds = total(entries, "REFUND");
		BigDecimal credits = total(entries, "CREDIT");
		BigDecimal debits = total(entries, "DEBIT");
		BigDecimal adjusted = "VOID".equals(invoice.status()) ? BigDecimal.ZERO : invoice.total().add(debits).subtract(credits);
		BigDecimal net = gross.subtract(refunds);
		return new InvoiceSummary(scale(gross), scale(refunds), scale(net), scale(credits), scale(debits), scale(adjusted),
			scale(adjusted.subtract(net).max(BigDecimal.ZERO)));
	}

	private ActionResult<FinancialAdjustment> recordAdjustment(TenantSession tenant, Invoice invoice, Payment payment,
			String type, BigDecimal amount, String reason, String requestKey) {
		FinancialAdjustment existing = adjustments.findByHospitalIdAndRequestKey(tenant.hospitalId(), requestKey).orElse(null);
		if (existing != null) {
			if (existing.invoiceId().equals(invoice.id()) && existing.type().equals(type) && existing.amount().compareTo(amount) == 0
					&& java.util.Objects.equals(existing.paymentId(), payment == null ? null : payment.id()) && existing.reason().equals(reason.trim())) {
				return new ActionResult<>(existing, invoice, summary(invoice), true);
			}
			throw conflict("This request key was already used for a different financial action.");
		}
		if (List.of("DRAFT", "VOID").contains(invoice.status()) || amount.signum() <= 0) {
			throw invalid("Financial actions require an active issued invoice and positive amount.");
		}
		InvoiceSummary before = summary(invoice);
		if ("VOID".equals(type) && (before.grossPaid().signum() != 0
				|| !adjustments.findByInvoiceIdOrderByRecordedAt(invoice.id()).isEmpty())) {
			throw invalid("Only an invoice with no payments or prior financial actions can be voided.");
		}
		if ("CREDIT".equals(type) && amount.compareTo(before.balance()) > 0) {
			throw invalid("Credit cannot exceed the outstanding balance.");
		}
		FinancialAdjustment entry = adjustments.save(new FinancialAdjustment(UUID.randomUUID().toString(),
			tenant.hospitalId(), tenant.branchId(), invoice.id(), payment == null ? null : payment.id(), type, scale(amount),
			reason.trim(), requestKey, tenant.userId(), Instant.now()));
		InvoiceSummary after = summary(invoice);
		String status = "VOID".equals(type) ? "VOID" : after.balance().signum() == 0 ? "PAID"
			: after.netPaid().signum() == 0 ? "ISSUED" : "PARTIALLY_PAID";
		Invoice updated = invoices.save(invoice.withStatus(status));
		audit.record(tenant.hospitalId(), tenant.branchId(), tenant.userId(), "financial_adjustments", type.toLowerCase(),
			Map.of("adjustmentId", entry.id(), "invoiceId", invoice.id(), "amount", money(entry.amount())));
		return new ActionResult<>(entry, updated, summary(updated), false);
	}

	private InvoiceLine line(TenantSession tenant, BillingController.LineRequest entry) {
		ServiceItem service = services.findByIdAndHospitalIdAndStatus(entry.serviceItemId(), tenant.hospitalId(), "active")
			.orElseThrow(() -> notFound("Service not found."));
		BigDecimal unitDiscount = switch (service.discountType()) {
			case "PERCENTAGE" -> service.basePrice().multiply(service.discountValue()).divide(HUNDRED);
			case "FIXED" -> service.discountValue().min(service.basePrice());
			default -> BigDecimal.ZERO;
		};
		BigDecimal taxable = service.basePrice().subtract(unitDiscount);
		BigDecimal unitTax = taxable.multiply(service.taxRatePercent()).divide(HUNDRED);
		BigDecimal quantity = BigDecimal.valueOf(entry.quantity());
		return new InvoiceLine(service.id(), service.code(), service.name(), entry.quantity(), scale(service.basePrice()),
			service.discountType(), scale(service.discountValue()), scale(service.taxRatePercent()),
			scale(service.basePrice().multiply(quantity)), scale(unitDiscount.multiply(quantity)),
			scale(unitTax.multiply(quantity)), scale(taxable.add(unitTax).multiply(quantity)));
	}

	private Invoice invoice(TenantSession tenant, String id) {
		return invoices.findByIdAndHospitalIdAndBranchId(id, tenant.hospitalId(), tenant.branchId())
			.orElseThrow(() -> notFound("Invoice not found."));
	}

	private long next(String id) {
		SequenceCounter counter = mongoTemplate.findAndModify(Query.query(Criteria.where("_id").is(id)),
			new Update().inc("value", 1), FindAndModifyOptions.options().upsert(true).returnNew(true), SequenceCounter.class);
		return counter.value();
	}

	private static BigDecimal total(List<FinancialAdjustment> entries, String type) {
		return entries.stream().filter(item -> type.equals(item.type())).map(FinancialAdjustment::amount)
			.reduce(BigDecimal.ZERO, BigDecimal::add);
	}

	private static BigDecimal sum(List<InvoiceLine> lines, java.util.function.Function<InvoiceLine, BigDecimal> field) {
		return scale(lines.stream().map(field).reduce(BigDecimal.ZERO, BigDecimal::add));
	}

	private static BigDecimal scale(BigDecimal value) { return value.setScale(2, RoundingMode.HALF_UP); }
	private static String money(BigDecimal value) { return scale(value).toPlainString(); }
	private static String normalized(String value) { return value == null || value.isBlank() ? null : value.trim(); }
	private static ResponseStatusException notFound(String message) { return new ResponseStatusException(HttpStatus.NOT_FOUND, message); }
	private static ResponseStatusException invalid(String message) { return new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, message); }
	private static ResponseStatusException conflict(String message) { return new ResponseStatusException(HttpStatus.CONFLICT, message); }

	public record InvoiceSummary(BigDecimal grossPaid, BigDecimal refundedTotal, BigDecimal netPaid,
			BigDecimal creditTotal, BigDecimal debitTotal, BigDecimal adjustedTotal, BigDecimal balance) {}
	public record InvoiceView(Invoice invoice, List<Payment> payments, List<FinancialAdjustment> adjustments,
			InvoiceSummary summary) {}
	public record ActionResult<T>(T data, Invoice invoice, InvoiceSummary summary, boolean replayed) {}
	public record ModeTotal(int count, BigDecimal total) {}
	public record Reconciliation(LocalDate date, String timezone, BigDecimal grossCollected, BigDecimal refunded,
			BigDecimal netCollected, int paymentCount, int refundCount, Map<String, ModeTotal> modes,
			List<Payment> payments, List<FinancialAdjustment> refunds) {}
}
