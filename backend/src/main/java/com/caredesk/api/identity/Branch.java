package com.caredesk.api.identity;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("branches")
public record Branch(@Id String id, String hospitalId, String code, String name, String timezone, String status) {
}
