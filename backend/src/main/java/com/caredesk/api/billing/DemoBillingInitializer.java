package com.caredesk.api.billing;

import java.math.BigDecimal;

import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;

@Component
@Profile("local")
public class DemoBillingInitializer implements ApplicationRunner {
	private final ServiceItemRepository services;

	public DemoBillingInitializer(ServiceItemRepository services) {
		this.services = services;
	}

	@Override
	public void run(ApplicationArguments args) {
		services.save(new ServiceItem("service-lotus-consult", "hospital-lotus", "CONSULT-GM",
			"General medicine consultation", "Consultation", new BigDecimal("500.00"), "NONE",
			BigDecimal.ZERO.setScale(2), new BigDecimal("0.00"), "active"));
		services.save(new ServiceItem("service-lotus-cbc", "hospital-lotus", "LAB-CBC", "Complete blood count",
			"Laboratory", new BigDecimal("350.00"), "PERCENTAGE", new BigDecimal("10.00"),
			new BigDecimal("5.00"), "active"));
	}
}
