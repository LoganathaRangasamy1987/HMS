package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.time.Instant;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;
import org.springframework.data.mongodb.core.mapping.Field;
import org.springframework.data.mongodb.core.mapping.FieldType;

@Document("payments")
public record Payment(@Id String id, String hospitalId, String branchId, String invoiceId, String receiptNumber,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal amount, String mode, String reference,
		String idempotencyKey, String receivedBy, Instant receivedAt) {
}
