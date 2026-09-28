package com.caredesk.api.tenant;

import java.util.List;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import com.caredesk.api.identity.Branch;
import com.caredesk.api.identity.BranchRepository;
import com.caredesk.api.identity.Hospital;
import com.caredesk.api.identity.HospitalRepository;
import com.caredesk.api.identity.Membership;
import com.caredesk.api.identity.MembershipRepository;
import com.caredesk.api.identity.Role;
import com.caredesk.api.identity.RoleRepository;
import com.caredesk.api.identity.UserAccount;
import com.caredesk.api.identity.UserAccountRepository;

import jakarta.servlet.http.HttpSession;

@Service
public class TenantContextService {
	public static final String ACTIVE_MEMBERSHIP = "activeMembershipId";

	private final UserAccountRepository users;
	private final MembershipRepository memberships;
	private final HospitalRepository hospitals;
	private final BranchRepository branches;
	private final RoleRepository roles;

	public TenantContextService(UserAccountRepository users, MembershipRepository memberships,
			HospitalRepository hospitals, BranchRepository branches, RoleRepository roles) {
		this.users = users;
		this.memberships = memberships;
		this.hospitals = hospitals;
		this.branches = branches;
		this.roles = roles;
	}

	public TenantSession current(Authentication authentication, HttpSession session) {
		UserAccount user = activeUser(authentication);
		List<Membership> activeMemberships = memberships.findByUserIdAndStatusOrderById(user.id(), "active");
		if (activeMemberships.isEmpty()) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "No active hospital membership.");
		}

		String selectedId = (String) session.getAttribute(ACTIVE_MEMBERSHIP);
		Membership selected = activeMemberships.stream().filter(item -> item.id().equals(selectedId)).findFirst()
			.orElse(activeMemberships.getFirst());
		session.setAttribute(ACTIVE_MEMBERSHIP, selected.id());
		return assemble(user, selected, activeMemberships);
	}

	public TenantSession switchMembership(Authentication authentication, HttpSession session, String membershipId) {
		UserAccount user = activeUser(authentication);
		Membership selected = memberships.findByIdAndUserIdAndStatus(membershipId, user.id(), "active")
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.FORBIDDEN, "Membership is not assigned to this user."));
		session.setAttribute(ACTIVE_MEMBERSHIP, selected.id());
		return current(authentication, session);
	}

	private UserAccount activeUser(Authentication authentication) {
		UserAccount user = users.findByEmailIgnoreCase(authentication.getName())
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.UNAUTHORIZED));
		if (!"active".equals(user.status())) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Account is inactive.");
		}
		return user;
	}

	private TenantSession assemble(UserAccount user, Membership selected, List<Membership> available) {
		Hospital hospital = hospitals.findById(selected.hospitalId()).filter(item -> "active".equals(item.status()))
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.FORBIDDEN, "Hospital is inactive."));
		if (!hospital.id().equals(user.hospitalId())) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Cross-hospital membership rejected.");
		}
		Branch branch = activeBranch(selected);
		Role role = roles.findById(selected.roleId())
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.FORBIDDEN, "Role is unavailable."));
		List<TenantSession.MembershipOption> options = available.stream().map(item -> {
			Branch optionBranch = activeBranch(item);
			Role optionRole = roles.findById(item.roleId())
				.orElseThrow(() -> new ResponseStatusException(HttpStatus.FORBIDDEN, "Role is unavailable."));
			return new TenantSession.MembershipOption(item.id(), optionBranch.id(), optionBranch.name(), optionRole.code());
		}).toList();
		return new TenantSession(user.id(), user.email(), user.name(), hospital.id(), hospital.name(), selected.id(),
			branch.id(), branch.name(), role.code(), role.permissions(), options);
	}

	private Branch activeBranch(Membership membership) {
		Branch branch = branches.findById(membership.branchId()).filter(item -> "active".equals(item.status()))
			.orElseThrow(() -> new ResponseStatusException(HttpStatus.FORBIDDEN, "Branch is inactive."));
		if (!branch.hospitalId().equals(membership.hospitalId())) {
			throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Cross-hospital branch rejected.");
		}
		return branch;
	}
}
