package com.caredesk.api.migration;

import java.nio.file.Path;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.Set;

public final class LegacyDataInventory {

	private static final Set<String> ALLOWED_TABLES = Set.of(
		"hospitals", "branches", "users", "memberships", "patients", "doctor_profiles",
		"appointments", "invoices", "payments", "encounters", "consultations", "prescriptions",
		"lab_orders", "lab_specimens", "lab_results", "pharmacy_purchases", "pharmacy_sales",
		"medicine_batches", "stock_movements");

	private LegacyDataInventory() {
	}

	public static void main(String[] args) throws Exception {
		Path database = Path.of(args[0]).toAbsolutePath().normalize();
		try (Connection connection = DriverManager.getConnection("jdbc:sqlite:" + database);
				Statement statement = connection.createStatement()) {
			System.out.println("Legacy fictional-data inventory: " + database.getFileName());
			for (String table : ALLOWED_TABLES.stream().sorted().toList()) {
				try (ResultSet result = statement.executeQuery("SELECT COUNT(*) AS total FROM " + table)) {
					System.out.printf("%s=%d%n", table, result.getLong("total"));
				}
			}
		}
	}
}
