package com.caredesk.api.doctor;

import java.time.Clock;
import java.time.LocalDate;
import java.time.LocalTime;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.List;

import org.springframework.stereotype.Service;

import com.caredesk.api.appointment.Appointment;
import com.caredesk.api.appointment.AppointmentRepository;
import com.caredesk.api.identity.Branch;
import com.caredesk.api.identity.BranchRepository;
import com.caredesk.api.tenant.TenantSession;

@Service
public class DoctorAvailabilityService {
	private final DoctorProfileRepository doctors;
	private final AppointmentRepository appointments;
	private final BranchRepository branches;
	private final Clock clock = Clock.systemUTC();

	public DoctorAvailabilityService(DoctorProfileRepository doctors, AppointmentRepository appointments,
			BranchRepository branches) {
		this.doctors = doctors;
		this.appointments = appointments;
		this.branches = branches;
	}

	public DoctorProfile doctor(TenantSession tenant, String id) {
		return doctors.findByIdAndHospitalIdAndBranchIdAndStatus(id, tenant.hospitalId(), tenant.branchId(), "active")
			.orElseThrow(() -> new org.springframework.web.server.ResponseStatusException(org.springframework.http.HttpStatus.NOT_FOUND));
	}

	public List<AvailableDate> availableDates(TenantSession tenant, String doctorId, LocalDate from, int days) {
		DoctorProfile doctor = doctor(tenant, doctorId);
		Branch branch = branches.findById(tenant.branchId()).orElseThrow();
		ZoneId zone = ZoneId.of(branch.timezone());
		LocalDate today = LocalDate.now(clock.withZone(zone));
		LocalTime now = LocalTime.now(clock.withZone(zone));
		LocalDate start = from == null || from.isBefore(today) ? today : from;
		List<AvailableDate> dates = new ArrayList<>();
		for (int offset = 0; offset < Math.min(Math.max(days, 1), 60); offset++) {
			LocalDate date = start.plusDays(offset);
			List<Appointment> booked = appointments.findByDoctorProfileIdAndAppointmentDateAndStatusNot(doctor.id(), date, "CANCELLED");
			List<Slot> slots = new ArrayList<>();
			for (AvailabilityWindow window : doctor.weeklyAvailability()) {
				if (window.dayOfWeek() != date.getDayOfWeek()) {
					continue;
				}
				for (LocalTime time = window.startsAt(); !time.plusMinutes(window.slotDurationMinutes()).isAfter(window.endsAt()); time = time.plusMinutes(window.slotDurationMinutes())) {
					if (date.equals(today) && !time.isAfter(now)) {
						continue;
					}
					LocalTime slotTime = time;
					long used = booked.stream().filter(item -> item.startsAt().equals(slotTime)).count();
					int remaining = Math.max(0, window.capacityPerSlot() - (int) used);
					slots.add(new Slot(time, time.plusMinutes(window.slotDurationMinutes()), remaining, window.capacityPerSlot()));
				}
			}
			if (!slots.isEmpty()) {
				dates.add(new AvailableDate(date, date.getDayOfWeek().toString(), slots));
			}
		}
		return dates;
	}

	public record AvailableDate(LocalDate date, String label, List<Slot> slots) {}
	public record Slot(LocalTime startsAt, LocalTime endsAt, int remaining, int capacity) {}
}
