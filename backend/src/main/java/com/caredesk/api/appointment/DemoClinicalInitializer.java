package com.caredesk.api.appointment;

import java.time.DayOfWeek;
import java.time.Instant;
import java.time.LocalTime;
import java.util.List;

import org.bson.types.Decimal128;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;

import com.caredesk.api.doctor.AvailabilityWindow;
import com.caredesk.api.doctor.DoctorProfile;
import com.caredesk.api.doctor.DoctorProfileRepository;
import com.caredesk.api.patient.Patient;
import com.caredesk.api.patient.PatientRepository;

@Component
@Profile("local")
public class DemoClinicalInitializer implements ApplicationRunner {
	private final DoctorProfileRepository doctors;
	private final PatientRepository patients;

	public DemoClinicalInitializer(DoctorProfileRepository doctors, PatientRepository patients) {
		this.doctors = doctors;
		this.patients = patients;
	}

	@Override
	public void run(ApplicationArguments args) {
		List<AvailabilityWindow> schedule = List.of(
			window(DayOfWeek.MONDAY), window(DayOfWeek.TUESDAY), window(DayOfWeek.WEDNESDAY),
			window(DayOfWeek.THURSDAY), window(DayOfWeek.FRIDAY), window(DayOfWeek.SATURDAY));
		doctors.save(new DoctorProfile("doctor-lotus-arjun", "hospital-lotus", "branch-lotus-cbe",
			"user-lotus-doctor", "Dr Arjun Kumar", "TNMC-DEMO-001", "MBBS, MD", "General Medicine",
			Decimal128.parse("500.00"), "active", schedule));
		if (!patients.existsById("patient-lotus-meera")) {
			patients.save(new Patient("patient-lotus-meera", "hospital-lotus", "branch-lotus-cbe",
				"UHID-2026-00001", "Meera", "Nair", java.time.LocalDate.of(1991, 4, 12), "FEMALE",
				"9000000001", "meera.patient@example.test", "Fictional local address", "active",
				"user-lotus-reception", Instant.now()));
		}
	}

	private static AvailabilityWindow window(DayOfWeek day) {
		return new AvailabilityWindow(day, LocalTime.of(9, 0), LocalTime.of(12, 0), 15, 2);
	}
}
