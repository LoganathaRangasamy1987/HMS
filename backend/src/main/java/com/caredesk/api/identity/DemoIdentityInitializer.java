package com.caredesk.api.identity;

import java.util.Set;

import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

@Component
@Profile("local")
public class DemoIdentityInitializer implements ApplicationRunner {
	private static final String DEMO_PASSWORD = "CareDesk@2026!";
	private final HospitalRepository hospitals;
	private final BranchRepository branches;
	private final RoleRepository roles;
	private final UserAccountRepository users;
	private final MembershipRepository memberships;
	private final PasswordEncoder passwords;

	public DemoIdentityInitializer(HospitalRepository hospitals, BranchRepository branches, RoleRepository roles,
			UserAccountRepository users, MembershipRepository memberships, PasswordEncoder passwords) {
		this.hospitals = hospitals;
		this.branches = branches;
		this.roles = roles;
		this.users = users;
		this.memberships = memberships;
		this.passwords = passwords;
	}

	@Override
	public void run(ApplicationArguments args) {
		Hospital lotus = save(new Hospital("hospital-lotus", "LOTUS", "Lotus Care Hospital", "active"));
		Hospital river = save(new Hospital("hospital-river", "RIVER", "Riverbank Hospital", "active"));
		Branch cbe = save(new Branch("branch-lotus-cbe", lotus.id(), "CBE", "Coimbatore Central", "Asia/Kolkata", "active"));
		Branch chn = save(new Branch("branch-lotus-chn", lotus.id(), "CHN", "Chennai Clinic", "Asia/Kolkata", "active"));
		Branch slm = save(new Branch("branch-river-slm", river.id(), "SLM", "Salem Main", "Asia/Kolkata", "active"));
		Role admin = save(new Role("role-admin", "HOSPITAL_ADMIN", "Hospital administrator",
			Set.of("DASHBOARD.VIEW", "ORGANIZATION.MANAGE", "STAFF.MANAGE", "AUDIT.VIEW", "PATIENT.VIEW",
				"PATIENT.MANAGE", "DOCTOR.VIEW", "DOCTOR_AVAILABILITY.VIEW", "APPOINTMENT.VIEW", "APPOINTMENT.MANAGE")));
		Role reception = save(new Role("role-reception", "RECEPTIONIST", "Receptionist",
			Set.of("DASHBOARD.VIEW", "PATIENT.VIEW", "PATIENT.MANAGE", "DOCTOR.VIEW",
				"DOCTOR_AVAILABILITY.VIEW", "APPOINTMENT.VIEW", "APPOINTMENT.MANAGE")));
		Role doctor = save(new Role("role-doctor", "DOCTOR", "Doctor",
			Set.of("DASHBOARD.VIEW", "PATIENT.VIEW", "PATIENT_HISTORY.VIEW", "DOCTOR.VIEW",
				"DOCTOR_AVAILABILITY.VIEW", "DOCTOR_AVAILABILITY.MANAGE", "APPOINTMENT.VIEW", "ENCOUNTER.MANAGE")));
		UserAccount lotusAdmin = saveUser("user-lotus-admin", lotus.id(), "admin@lotus.test", "Ananya Raman");
		UserAccount receptionist = saveUser("user-lotus-reception", lotus.id(), "reception@lotus.test", "Priya S");
		UserAccount lotusDoctor = saveUser("user-lotus-doctor", lotus.id(), "doctor@lotus.test", "Dr Arjun Kumar");
		UserAccount riverAdmin = saveUser("user-river-admin", river.id(), "admin@river.test", "Riverbank Administrator");
		save(new Membership("membership-lotus-admin-cbe", lotus.id(), cbe.id(), lotusAdmin.id(), admin.id(), "active"));
		save(new Membership("membership-lotus-admin-chn", lotus.id(), chn.id(), lotusAdmin.id(), admin.id(), "active"));
		save(new Membership("membership-lotus-reception-cbe", lotus.id(), cbe.id(), receptionist.id(), reception.id(), "active"));
		save(new Membership("membership-lotus-doctor-cbe", lotus.id(), cbe.id(), lotusDoctor.id(), doctor.id(), "active"));
		save(new Membership("membership-river-admin-slm", river.id(), slm.id(), riverAdmin.id(), admin.id(), "active"));
	}

	private UserAccount saveUser(String id, String hospitalId, String email, String name) {
		return users.findById(id).orElseGet(() -> users.save(
			new UserAccount(id, hospitalId, email, name, passwords.encode(DEMO_PASSWORD), "active", null)));
	}

	private Hospital save(Hospital value) { return hospitals.save(value); }
	private Branch save(Branch value) { return branches.save(value); }
	private Role save(Role value) { return roles.save(value); }
	private Membership save(Membership value) { return memberships.save(value); }
}
