package com.caredesk.api.billing;

import java.math.BigDecimal;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;
import org.springframework.data.mongodb.core.mapping.Field;
import org.springframework.data.mongodb.core.mapping.FieldType;

@Document("serviceItems")
public record ServiceItem(@Id String id, String hospitalId, String code, String name, String category,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal basePrice, String discountType,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal discountValue,
		@Field(targetType = FieldType.DECIMAL128) BigDecimal taxRatePercent, String status) {
}
