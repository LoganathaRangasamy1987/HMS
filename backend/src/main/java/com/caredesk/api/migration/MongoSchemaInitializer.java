package com.caredesk.api.migration;

import org.bson.Document;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.data.domain.Sort.Direction;
import org.springframework.data.mongodb.core.MongoTemplate;
import org.springframework.data.mongodb.core.index.Index;
import org.springframework.stereotype.Component;

@Component
public class MongoSchemaInitializer implements ApplicationRunner {

	private final MongoTemplate mongoTemplate;

	public MongoSchemaInitializer(MongoTemplate mongoTemplate) {
		this.mongoTemplate = mongoTemplate;
	}

	@Override
	public void run(ApplicationArguments args) {
		unique("users", "hospital_email_unique", "hospitalId", "email");
		unique("memberships", "membership_scope_unique", "hospitalId", "userId", "branchId");
		unique("patients", "patient_uhid_unique", "hospitalId", "uhid");
		unique("invoices", "invoice_number_unique", "hospitalId", "number");
		unique("payments", "payment_idempotency_unique", "hospitalId", "idempotencyKey");
		index("appointments", "appointment_worklist", "branchId", "doctorProfileId", "startsAt", "status");
		index("auditEvents", "audit_timeline", "hospitalId", "branchId", "occurredAt");
		index("medicineBatches", "batch_fefo", "branchId", "medicineId", "expiresOn", "quantityOnHand");
		mongoTemplate.getCollection("schemaMigrations").updateOne(
			new Document("_id", "MIG-002-v1"),
			new Document("$setOnInsert", new Document("description", "CareDesk core tenant and workflow indexes")),
			new com.mongodb.client.model.UpdateOptions().upsert(true));
	}

	private void unique(String collection, String name, String... fields) {
		Index index = buildIndex(name, fields).unique();
		mongoTemplate.indexOps(collection).createIndex(index);
	}

	private void index(String collection, String name, String... fields) {
		mongoTemplate.indexOps(collection).createIndex(buildIndex(name, fields));
	}

	private Index buildIndex(String name, String... fields) {
		Index index = new Index().named(name);
		for (String field : fields) {
			index.on(field, Direction.ASC);
		}
		return index;
	}
}
