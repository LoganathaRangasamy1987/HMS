package com.caredesk.api.migration;

import org.bson.Document;

import com.mongodb.client.ClientSession;
import com.mongodb.client.MongoClient;
import com.mongodb.client.MongoClients;
import com.mongodb.client.MongoCollection;

public final class MongoTransactionVerifier {

	private MongoTransactionVerifier() {
	}

	public static void main(String[] args) {
		String uri = System.getenv().getOrDefault("CAREDESK_MONGO_REPLICA_URI",
			"mongodb://127.0.0.1:27018/?replicaSet=caredesk-rs");
		try (MongoClient client = MongoClients.create(uri);
				ClientSession session = client.startSession()) {
			MongoCollection<Document> collection = client.getDatabase("caredesk").getCollection("transactionProofs");
			String proofId = "MIG-002-rollback-proof";
			collection.deleteOne(new Document("_id", proofId));
			session.startTransaction();
			collection.insertOne(session, new Document("_id", proofId));
			session.abortTransaction();
			if (collection.countDocuments(new Document("_id", proofId)) != 0) {
				throw new IllegalStateException("Aborted transaction left persisted data.");
			}
			System.out.println("MongoDB transaction rollback verified.");
		}
	}
}
