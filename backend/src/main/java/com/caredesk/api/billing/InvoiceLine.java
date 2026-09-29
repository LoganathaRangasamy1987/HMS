package com.caredesk.api.billing;

import java.math.BigDecimal;

import org.springframework.data.mongodb.core.mapping.Field;
import org.springframework.data.mongodb.core.mapping.FieldType;

public record InvoiceLine(String serviceItemId, String serviceCode, String description, int quantity,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal unitPrice, String discountType,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal discountValue,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal taxRatePercent,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal subtotal,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal discountAmount,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal taxAmount,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal total) {
}
