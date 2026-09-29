package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.time.Instant;
import java.util.List;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;
import org.springframework.data.mongodb.core.mapping.Field;
import org.springframework.data.mongodb.core.mapping.FieldType;

@Document("invoices")
public record Invoice(@Id String id, String hospitalId, String branchId, String patientId, String patientName,
		String patientUhid, String appointmentId, String number, String currency, String status, List<InvoiceLine> lines,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal subtotal,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal discountTotal,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal taxTotal,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal total, String createdBy, Instant createdAt,
		String issuedBy, Instant issuedAt) {
	public Invoice issued(String invoiceNumber, String actor, Instant time) {
		return new Invoice(id, hospitalId, branchId, patientId, patientName, patientUhid, appointmentId, invoiceNumber,
			currency, "ISSUED", lines, subtotal, discountTotal, taxTotal, total, createdBy, createdAt, actor, time);
	}

	public Invoice withStatus(String value) {
		return new Invoice(id, hospitalId, branchId, patientId, patientName, patientUhid, appointmentId, number,
			currency, value, lines, subtotal, discountTotal, taxTotal, total, createdBy, createdAt, issuedBy, issuedAt);
	}
}
