package com.caredesk.api.billing;

import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import java.util.Map;
import java.util.UUID;

import org.junit.jupiter.api.Test;
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
class BillingWorkflowTest {
	@Autowired
	private MockMvc mockMvc;
	@Autowired
	private ObjectMapper objectMapper;

	@Test
	void receptionCanIssueInvoiceCollectPaymentAndReceiveReceiptIdempotently() throws Exception {
		MockHttpSession session = login("reception@lotus.test");
		mockMvc.perform(get("/api/v1/services").session(session)).andExpect(status().isOk())
			.andExpect(jsonPath("$[0].code").exists());
		String invoiceId = createAndIssue(session, "service-lotus-consult");
		String key = UUID.randomUUID().toString();
		String payment = json(Map.of("amount", "500.00", "mode", "UPI", "reference", "UPI-MIG005", "requestKey", key));
		mockMvc.perform(post("/api/v1/invoices/{id}/payments", invoiceId).session(session).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(payment))
			.andExpect(status().isCreated()).andExpect(jsonPath("$.data.receiptNumber").value(org.hamcrest.Matchers.startsWith("RCP-")))
			.andExpect(jsonPath("$.invoice.status").value("PAID")).andExpect(jsonPath("$.summary.balance").value(0.0));
		mockMvc.perform(post("/api/v1/invoices/{id}/payments", invoiceId).session(session).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(payment))
			.andExpect(status().isOk()).andExpect(jsonPath("$.replayed").value(true));
	}

	@Test
	void financialRulesPreventOverpaymentAndSupportCreditRefundAndVoid() throws Exception {
		MockHttpSession admin = login("admin@lotus.test");
		String invoiceId = createAndIssue(admin, "service-lotus-consult");
		pay(admin, invoiceId, "200.00").andExpect(status().isCreated())
			.andExpect(jsonPath("$.invoice.status").value("PARTIALLY_PAID"));
		pay(admin, invoiceId, "301.00").andExpect(status().isUnprocessableEntity());

		String credit = json(Map.of("type", "CREDIT", "amount", "50.00", "reason", "Approved correction",
			"requestKey", UUID.randomUUID().toString()));
		mockMvc.perform(post("/api/v1/invoices/{id}/adjustments", invoiceId).session(admin).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(credit))
			.andExpect(status().isCreated()).andExpect(jsonPath("$.summary.adjustedTotal").value(450.0))
			.andExpect(jsonPath("$.summary.balance").value(250.0));

		String voidable = createAndIssue(admin, "service-lotus-consult");
		String voidPayload = json(Map.of("reason", "Service not delivered", "requestKey", UUID.randomUUID().toString()));
		mockMvc.perform(post("/api/v1/invoices/{id}/void", voidable).session(admin).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(json(Map.of("reason", "Service not delivered",
					"requestKey", objectMapper.readTree(voidPayload).get("requestKey").asText()))))
			.andExpect(status().isCreated()).andExpect(jsonPath("$.invoice.status").value("VOID"))
			.andExpect(jsonPath("$.summary.balance").value(0.0));
		mockMvc.perform(post("/api/v1/invoices/{id}/void", voidable).session(admin).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(voidPayload))
			.andExpect(status().isOk()).andExpect(jsonPath("$.replayed").value(true));
	}

	@Test
	void adjustmentAndReconciliationAreAdminOnlyAndInvoicesAreTenantScoped() throws Exception {
		MockHttpSession reception = login("reception@lotus.test");
		String invoiceId = createAndIssue(reception, "service-lotus-consult");
		mockMvc.perform(get("/api/v1/billing/reconciliation").session(reception)).andExpect(status().isForbidden());
		mockMvc.perform(post("/api/v1/invoices/{id}/void", invoiceId).session(reception).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(json(Map.of("reason", "Not allowed",
					"requestKey", UUID.randomUUID().toString()))))
			.andExpect(status().isForbidden());

		MockHttpSession river = login("admin@river.test");
		mockMvc.perform(get("/api/v1/invoices/{id}", invoiceId).session(river)).andExpect(status().isNotFound());
		mockMvc.perform(get("/api/v1/billing/reconciliation").session(river)).andExpect(status().isOk())
			.andExpect(jsonPath("$.timezone").value("Asia/Kolkata"));
	}

	private String createAndIssue(MockHttpSession session, String serviceId) throws Exception {
		String payload = json(Map.of("patientId", "patient-lotus-meera", "lines",
			java.util.List.of(Map.of("serviceItemId", serviceId, "quantity", 1))));
		MvcResult result = mockMvc.perform(post("/api/v1/invoices").session(session).with(csrf())
				.contentType(MediaType.APPLICATION_JSON).content(payload))
			.andExpect(status().isCreated()).andExpect(jsonPath("$.status").value("DRAFT")).andReturn();
		JsonNode invoice = objectMapper.readTree(result.getResponse().getContentAsString());
		String id = invoice.get("id").asText();
		mockMvc.perform(post("/api/v1/invoices/{id}/issue", id).session(session).with(csrf()))
			.andExpect(status().isOk()).andExpect(jsonPath("$.number").value(org.hamcrest.Matchers.startsWith("INV-")));
		return id;
	}

	private org.springframework.test.web.servlet.ResultActions pay(MockHttpSession session, String invoiceId,
			String amount) throws Exception {
		return mockMvc.perform(post("/api/v1/invoices/{id}/payments", invoiceId).session(session).with(csrf())
			.contentType(MediaType.APPLICATION_JSON).content(json(Map.of("amount", amount, "mode", "CASH",
				"requestKey", UUID.randomUUID().toString()))));
	}

	private MockHttpSession login(String email) throws Exception {
		MvcResult result = mockMvc.perform(post("/api/v1/auth/login").with(csrf()).contentType(MediaType.APPLICATION_JSON)
				.content(json(Map.of("email", email, "password", "CareDesk@2026!"))))
			.andExpect(status().isOk()).andReturn();
		return (MockHttpSession) result.getRequest().getSession(false);
	}

	private String json(Object value) throws Exception { return objectMapper.writeValueAsString(value); }
}
