package com.caredesk.api.auth;

import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.stereotype.Service;

import com.caredesk.api.identity.UserAccount;
import com.caredesk.api.identity.UserAccountRepository;

@Service
public class MongoUserDetailsService implements UserDetailsService {
	private final UserAccountRepository users;

	public MongoUserDetailsService(UserAccountRepository users) {
		this.users = users;
	}

	@Override
	public UserDetails loadUserByUsername(String email) throws UsernameNotFoundException {
		UserAccount account = users.findByEmailIgnoreCase(email.trim().toLowerCase())
			.orElseThrow(() -> new UsernameNotFoundException("Invalid credentials."));
		return User.withUsername(account.email()).password(account.passwordHash())
			.disabled(!"active".equals(account.status())).authorities("USER").build();
	}
}
