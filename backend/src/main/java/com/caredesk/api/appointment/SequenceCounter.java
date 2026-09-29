package com.caredesk.api.appointment;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

@Document("sequenceCounters")
public record SequenceCounter(@Id String id, long value) {
}
