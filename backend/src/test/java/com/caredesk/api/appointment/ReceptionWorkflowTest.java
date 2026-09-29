package com.caredesk.api.appointment;

import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.patch;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import java.time.DayOfWeek;
import java.time.LocalDate;
import java.util.UUID;
import java.util.concurrent.ThreadLocalRandom;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.Assertions;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.http.MediaType;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.MvcResult;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

@SpringBootTest
@AutoConfigureMockMvc
class ReceptionWorkflowTest {
	@Autowired
	private MockMvc mockMvc;
	@Autowired
	private ObjectMapper objectMapper;

	@Test
	void receptionCanSeeDoctorDatesBookTokenAndMoveQueue() throws Exception {
		MockHttpSession session = login("reception@lotus.test");
		mockMvc.perform(get("/api/v1/doctors/doctor-lotus-arjun/availability").session(session))
			.andExpect(status().isOk()).andExpect(jsonPath("$[0].date").exists())
			.andExpect(jsonPath("$[0].slots[0].remaining").isNumber());

		LocalDate date = next(DayOfWeek.MONDAY, 80);
		String requestKey = UUID.randomUUID().toString();
		MvcResult result = book(session, date, requestKey).andExpect(status().isCreated())
			.andExpect(jsonPath("$.patientUhid").value("UHID-2026-00001"))
			.andExpect(jsonPath("$.doctorName").value("Dr Arjun Kumar"))
			.andExpect(jsonPath("$.tokenNumber").isNumber()).andReturn();
		JsonNode appointment = objectMapper.readTree(result.getResponse().getContentAsString());

		mockMvc.perform(patch("/api/v1/appointments/{id}/status", appointment.get("id").asText())
				.session(session).with(csrf()).contentType(MediaType.APPLICATION_JSON)
				.content("{\"status\":\"CHECKED_IN\"}"))
			.andExpect(status().isOk()).andExpect(jsonPath("$.status").value("CHECKED_IN"));
		mockMvc.perform(patch("/api/v1/appointments/{id}/status", appointment.get("id").asText())
				.session(session).with(csrf()).contentType(MediaType.APPLICATION_JSON)
				.content("{\"status\":\"WAITING\"}"))
			.andExpect(status().isOk()).andExpect(jsonPath("$.status").value("WAITING"));
	}

	@Test
	void slotCapacityIsEnforcedTransactionally() throws Exception {
		MockHttpSession session = login("reception@lotus.test");
		LocalDate date = next(DayOfWeek.TUESDAY, 140);
		book(session, date, UUID.randomUUID().toString()).andExpect(status().isCreated());
		book(session, date, UUID.randomUUID().toString()).andExpect(status().isCreated());
		book(session, date, UUID.randomUUID().toString()).andExpect(status().isConflict());
	}

	@Test
	void foreignHospitalPatientAndUnassignedDoctorAreRejected() throws Exception {
		MockHttpSession session = login("admin@river.test");
		mockMvc.perform(get("/api/v1/doctors/doctor-lotus-arjun/availability").session(session))
			.andExpect(status().isNotFound());
		MvcResult patients = mockMvc.perform(get("/api/v1/patients").session(session))
			.andExpect(status().isOk()).andReturn();
		Assertions.assertFalse(patients.getResponse().getContentAsString().contains("patient-lotus-meera"));
	}

	private org.springframework.test.web.servlet.ResultActions book(MockHttpSession session, LocalDate date,
			String requestKey) throws Exception {
		String payload = objectMapper.writeValueAsString(java.util.Map.of(
			"patientId", "patient-lotus-meera", "doctorProfileId", "doctor-lotus-arjun",
			"appointmentDate", date.toString(), "startsAt", "09:00", "type", "NEW", "requestKey", requestKey));
		return mockMvc.perform(post("/api/v1/appointments").session(session).with(csrf())
			.contentType(MediaType.APPLICATION_JSON).content(payload));
	}

	private MockHttpSession login(String email) throws Exception {
		String credentials = objectMapper.writeValueAsString(java.util.Map.of("email", email, "password", "CareDesk@2026!"));
		MvcResult result = mockMvc.perform(post("/api/v1/auth/login").with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isOk()).andReturn();
		return (MockHttpSession) result.getRequest().getSession(false);
	}

	private static LocalDate next(DayOfWeek day, int minimumDays) {
		LocalDate date = LocalDate.now().plusDays(minimumDays + ThreadLocalRandom.current().nextInt(30, 3000));
		while (date.getDayOfWeek() != day) {
			date = date.plusDays(1);
		}
		return date;
	}
}
