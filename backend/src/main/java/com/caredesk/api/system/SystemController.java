package com.caredesk.api.system;

import java.util.Map;

import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
@RequestMapping("/api/v1/system")
public class SystemController {

	@GetMapping
	public Map<String, String> show() {
		return Map.of(
			"application", "CareDesk Hospital ERP API",
			"status", "ready",
			"version", "v1");
	}
}
