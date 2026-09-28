package com.caredesk.api.migration;

import java.time.Duration;

import org.bson.Document;

import com.mongodb.MongoCommandException;
import com.mongodb.client.MongoClient;
import com.mongodb.client.MongoClients;

public final class MongoReplicaSetInitializer {

	private static final String URI = "mongodb://127.0.0.1:27018/?directConnection=true";

	private MongoReplicaSetInitializer() {
	}

	public static void main(String[] args) throws InterruptedException {
		try (MongoClient client = MongoClients.create(URI)) {
			try {
				client.getDatabase("admin").runCommand(new Document("replSetGetStatus", 1));
			} catch (MongoCommandException exception) {
				if (exception.getErrorCode() != 94) {
					throw exception;
				}

				Document member = new Document("_id", 0).append("host", "127.0.0.1:27018");
				Document configuration = new Document("_id", "caredesk-rs").append("members", java.util.List.of(member));
				client.getDatabase("admin").runCommand(new Document("replSetInitiate", configuration));
			}

			long deadline = System.nanoTime() + Duration.ofSeconds(30).toNanos();
			while (System.nanoTime() < deadline) {
				try {
					Document hello = client.getDatabase("admin").runCommand(new Document("hello", 1));
					if (Boolean.TRUE.equals(hello.getBoolean("isWritablePrimary"))) {
						System.out.println("CareDesk MongoDB replica set is writable.");
						return;
					}
				} catch (MongoCommandException ignored) {
				}
				Thread.sleep(500);
			}
		}

		throw new IllegalStateException("Replica set did not elect a primary within 30 seconds.");
	}
}
