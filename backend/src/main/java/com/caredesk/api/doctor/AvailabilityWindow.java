package com.caredesk.api.doctor;

import java.time.DayOfWeek;
import java.time.LocalTime;

public record AvailabilityWindow(DayOfWeek dayOfWeek, LocalTime startsAt, LocalTime endsAt,
		int slotDurationMinutes, int capacityPerSlot) {
}
