package com.caredesk.api.auth;

import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.http.MediaType;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.MvcResult;

import com.caredesk.api.identity.UserAccountRepository;

@SpringBootTest(properties = "spring.data.mongodb.uri=mongodb://127.0.0.1:27018/caredesk?replicaSet=caredesk-rs")
@AutoConfigureMockMvc
class AuthenticationFlowTest {
	@Autowired
	private MockMvc mockMvc;
	@Autowired
	private UserAccountRepository users;
	@Autowired
	private PasswordEncoder passwords;
	@Autowired
	private AuthenticationManager authenticationManager;

	@Test
	void loginRequiresCsrfAndCreatesTenantScopedSession() throws Exception {
		var account = users.findByEmailIgnoreCase("admin@lotus.test").orElseThrow();
		org.junit.jupiter.api.Assertions.assertTrue(passwords.matches("CareDesk@2026!", account.passwordHash()));
		org.junit.jupiter.api.Assertions.assertTrue(authenticationManager.authenticate(
			UsernamePasswordAuthenticationToken.unauthenticated("admin@lotus.test", "CareDesk@2026!")).isAuthenticated());
		String credentials = "{\"email\":\"admin@lotus.test\",\"password\":\"CareDesk@2026!\"}";
		mockMvc.perform(post("/api/v1/auth/login").contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isForbidden());

		MvcResult login = mockMvc.perform(post("/api/v1/auth/login").with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$.hospitalName").value("Lotus Care Hospital"))
			.andExpect(jsonPath("$.branchName").value("Coimbatore Central"))
			.andExpect(jsonPath("$.role").value("HOSPITAL_ADMIN"))
			.andReturn();

		MockHttpSession session = (MockHttpSession) login.getRequest().getSession(false);
		mockMvc.perform(get("/api/v1/me").session(session))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$.email").value("admin@lotus.test"));
	}

	@Test
	void userCannotSelectAnotherHospitalsMembership() throws Exception {
		String credentials = "{\"email\":\"admin@lotus.test\",\"password\":\"CareDesk@2026!\"}";
		MvcResult login = mockMvc.perform(post("/api/v1/auth/login").with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isOk()).andReturn();
		MockHttpSession session = (MockHttpSession) login.getRequest().getSession(false);

		mockMvc.perform(post("/api/v1/context").session(session).with(csrf())
				.contentType(MediaType.APPLICATION_JSON)
				.content("{\"membershipId\":\"membership-river-admin-slm\"}"))
			.andExpect(status().isForbidden());
	}

	@Test
	void administratorCanSwitchAssignedBranchAndReadHospitalAudit() throws Exception {
		String credentials = "{\"email\":\"admin@lotus.test\",\"password\":\"CareDesk@2026!\"}";
		MvcResult login = mockMvc.perform(post("/api/v1/auth/login").with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isOk()).andReturn();
		MockHttpSession session = (MockHttpSession) login.getRequest().getSession(false);

		mockMvc.perform(post("/api/v1/context").session(session).with(csrf())
				.contentType(MediaType.APPLICATION_JSON)
				.content("{\"membershipId\":\"membership-lotus-admin-chn\"}"))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$.branchName").value("Chennai Clinic"));
		mockMvc.perform(get("/api/v1/audit-events").session(session))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$[0].hospitalId").value("hospital-lotus"));
	}

	@Test
	void receptionistCannotReadAdministrativeAuditEvents() throws Exception {
		String credentials = "{\"email\":\"reception@lotus.test\",\"password\":\"CareDesk@2026!\"}";
		MvcResult login = mockMvc.perform(post("/api/v1/auth/login").with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credentials))
			.andExpect(status().isOk()).andReturn();
		MockHttpSession session = (MockHttpSession) login.getRequest().getSession(false);

		mockMvc.perform(get("/api/v1/audit-events").session(session))
			.andExpect(status().isForbidden());
	}
}
