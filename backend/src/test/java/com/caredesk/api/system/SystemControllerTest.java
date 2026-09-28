package com.caredesk.api.system;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.test.web.servlet.MockMvc;

@SpringBootTest
@AutoConfigureMockMvc
class SystemControllerTest {

	@Autowired
	private MockMvc mockMvc;

	@Test
	void systemEndpointIsPublicAndReportsReady() throws Exception {
		mockMvc.perform(get("/api/v1/system"))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$.application").value("CareDesk Hospital ERP API"))
			.andExpect(jsonPath("$.status").value("ready"))
			.andExpect(jsonPath("$.version").value("v1"));
	}

	@Test
	void actuatorHealthEndpointIsPublic() throws Exception {
		mockMvc.perform(get("/api/actuator/health"))
			.andExpect(status().isOk())
			.andExpect(jsonPath("$.status").value("UP"));
	}

	@Test
	void unknownApiEndpointRequiresAuthentication() throws Exception {
		mockMvc.perform(get("/api/v1/private-placeholder"))
			.andExpect(status().isUnauthorized());
	}
}
