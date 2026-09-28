package com.caredesk.api.identity;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("hospitals")
public record Hospital(@Id String id, String code, String name, String status) {
}
