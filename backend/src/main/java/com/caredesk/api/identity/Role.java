package com.caredesk.api.identity;

import java.util.Set;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("roles")
public record Role(@Id String id, String code, String label, Set<String> permissions) {
}
