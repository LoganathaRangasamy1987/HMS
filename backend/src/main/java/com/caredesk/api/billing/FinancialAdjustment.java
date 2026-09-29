package com.caredesk.api.billing;

import java.math.BigDecimal;
import java.time.Instant;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;
import org.springframework.data.mongodb.core.mapping.Field;
import org.springframework.data.mongodb.core.mapping.FieldType;

@Document("financialAdjustments")
public record FinancialAdjustment(@Id String id, String hospitalId, String branchId, String invoiceId,
		String paymentId, String type, @Field(targetType = FieldType.DECIMAL128) BigDecimal amount,
		String reason, String requestKey, String recordedBy, Instant recordedAt) {
}
