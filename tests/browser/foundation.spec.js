import { test, expect } from '@playwright/test';

async function signIn(page, email = 'admin@lotus.test') {
  await page.goto('/login');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel(/^Password/).fill('CareDesk@2026!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test('administrator can sign in, open management forms, and save a branch', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/login');
  await page.screenshot({ path: 'test-results/login-desktop.png', fullPage: true });
  await signIn(page);
  await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
  await page.screenshot({ path: 'test-results/dashboard-desktop.png', fullPage: true });
  for (const route of ['/organization', '/branches', '/branches/create', '/departments', '/departments/create', '/staff', '/staff/create', '/doctors', '/doctors/create', '/audit']) {
    const response = await page.goto(route);
    expect(response.status(), route).toBe(200);
    await expect(page.locator('main h1')).toBeVisible();
  }
  await page.goto('/staff/create');
  const row = page.locator('[data-membership-row]').first();
  await row.locator('[data-membership-toggle]').check();
  await expect(row.locator('select').first()).toBeEnabled();
  await row.locator('[data-membership-toggle]').uncheck();
  await expect(row.locator('select').first()).toBeDisabled();
  const branches = await (await page.request.get('/api/v1/branches')).json();
  const branch = branches.data.find(branch => branch.code === 'CBE');
  await page.goto(`/branches/${branch.id}/edit`);
  await expect(page.getByLabel('Branch name', { exact: false })).toHaveValue(branch.name);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page).toHaveURL(/\/branches$/);
  await expect(page.getByRole('status')).toContainText('Branch updated');
  expect(pageErrors).toEqual([]);
});

test('branch switch works and browser mutations require CSRF protection', async ({ page }) => {
  await signIn(page);
  await page.getByLabel('Active hospital and branch').selectOption({ label: 'Lotus Care Hospital · Chennai Clinic' });
  await expect(page.getByRole('status')).toContainText('Switched to Chennai Clinic');
  const mutation = await page.request.post('/api/v1/branches', { data: { name: 'Forged branch', code: 'FORGED', status: 'active' } });
  expect(mutation.status()).toBe(419);
  await page.getByRole('button', { name: /Ananya Raman/ }).click();
  await page.getByRole('button', { name: /Sign out/ }).click();
  await expect(page).toHaveURL(/\/login$/);
  expect((await page.request.get('/api/v1/staff')).status()).toBe(401);
});

test('doctor sees only permitted navigation and is denied administration', async ({ page }) => {
  await signIn(page, 'doctor@lotus.test');
  await expect(page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Staff & access' })).toHaveCount(0);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Doctors' }).click();
  await expect(page.getByRole('heading', { name: 'Doctors', exact: true })).toBeVisible();
  expect((await page.goto('/doctors/create')).status()).toBe(403);
  const response = await page.goto('/staff');
  expect(response.status()).toBe(403);
  await expect(page.getByRole('heading', { level: 2 })).toContainText(/access|permission/i);
});

test('mobile navigation is usable without horizontal overflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await signIn(page);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
  await page.getByRole('link', { name: 'Departments', exact: true }).click();
  await expect(page).toHaveURL(/\/departments$/);
  await page.screenshot({ path: 'test-results/departments-mobile.png', fullPage: true });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('administrator opens the service catalog and branch pricing controls', async ({ page }) => {
  await signIn(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Services' }).click();
  await expect(page.getByRole('heading', { name: 'Services', exact: true })).toBeVisible();
  await expect(page.getByText('General consultation')).toBeVisible();
  await page.getByRole('link', { name: 'Edit' }).first().click();
  await expect(page.getByRole('heading', { name: 'Edit service' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Branch override' })).toBeVisible();
  await expect(page.getByLabel('Tax rate (%)')).toHaveValue('0.00');
});

test('administrator opens pharmacy catalog foundations', async ({ page }) => {
  await signIn(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Pharmacy catalog' }).click();
  await expect(page.getByRole('heading', { name: 'Pharmacy catalog', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Manufacturers' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Medicine types' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Stock units' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Add supplier' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Add pharmacy medicine' })).toBeVisible();
});

test('administrator opens daily reconciliation and receptionist is denied', async ({ page }) => {
  await signIn(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Reconciliation' }).click();
  await expect(page.getByRole('heading', { name: 'Daily reconciliation' })).toBeVisible();
  await expect(page.getByText('Gross collected', { exact: true })).toBeVisible();
  await expect(page.getByText('Net collections', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Collections by mode' })).toBeVisible();
  await page.getByRole('button', { name: /Ananya Raman/ }).click();
  await page.getByRole('button', { name: /Sign out/ }).click();
  await signIn(page, 'reception@lotus.test');
  await expect(page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Reconciliation' })).toHaveCount(0);
  expect((await page.goto('/billing/reconciliation')).status()).toBe(403);
});

test('receptionist sees a price quote without management controls', async ({ page }) => {
  await signIn(page, 'reception@lotus.test');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Services' }).click();
  await expect(page.getByRole('link', { name: 'Add service' })).toHaveCount(0);
  await page.getByRole('link', { name: 'Price' }).first().click();
  await expect(page.getByRole('heading', { name: 'Service price' })).toBeVisible();
  await expect(page.locator('main')).toContainText('₹500.00');
  expect((await page.goto('/services/create')).status()).toBe(403);
});

test('receptionist selects a doctor and receives bookable dates and time slots', async ({ page }) => {
  await signIn(page, 'reception@lotus.test');
  await page.goto('/appointments');
  const doctor = page.locator('[data-appointment-doctor]');
  const date = page.locator('[data-appointment-date]');
  const time = page.locator('[data-appointment-time]');
  await expect(date).toBeDisabled();
  await doctor.selectOption({ index: 1 });
  await expect(date).toBeEnabled();
  expect(await date.locator('option').count()).toBeGreaterThan(1);
  await date.selectOption({ index: 1 });
  await expect(time).toBeEnabled();
  expect(await time.locator('option').count()).toBeGreaterThan(1);
  await expect(page.locator('[data-appointment-availability-status]')).toContainText(/Showing \d+ available/);
});

test('receptionist saves and issues an invoice from the portal', async ({ page }) => {
  await signIn(page, 'reception@lotus.test');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Invoices' }).click();
  await page.getByRole('link', { name: 'New invoice' }).click();
  await page.getByLabel('Patient').selectOption({ index: 1 });
  await page.getByLabel('Service 1').selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByRole('heading', { name: /Invoice draft/ })).toBeVisible();
  await expect(page.locator('main')).toContainText('500.00');
  await page.getByRole('button', { name: 'Issue invoice' }).click();
  await expect(page.locator('main h1')).toContainText(/INV-\d+-\d{4}-\d+/);
  await expect(page.getByRole('link', { name: 'Edit draft' })).toHaveCount(0);
  await page.getByLabel('Amount (INR)').fill('200.00');
  await page.getByLabel('Mode').selectOption('CASH');
  await page.getByRole('button', { name: 'Record payment' }).click();
  await expect(page.locator('main')).toContainText('Balance: ₹300.00');
  const invoiceUrl = page.url();
  await page.getByRole('link', { name: 'Receipt' }).click();
  await expect(page.getByRole('heading', { name: 'Payment receipt' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Print' })).toBeVisible();
  await page.goto(invoiceUrl);
  await page.getByLabel('Amount (INR)').fill('300.00');
  await page.getByLabel('Mode').selectOption('UPI');
  await page.getByLabel('Reference (required except cash)').fill('UPI-DEMO-TEST');
  await page.getByRole('button', { name: 'Record payment' }).click();
  await expect(page.locator('main')).toContainText('Balance: ₹0.00');
  await expect(page.getByRole('button', { name: 'Record payment' })).toHaveCount(0);
  await page.getByRole('link', { name: 'Print invoice' }).click();
  await expect(page.getByRole('heading', { name: 'Invoice', exact: true })).toBeVisible();
  await expect(page.getByText('Balance due')).toBeVisible();
});

test('administrator records financial adjustments and voids through the portal', async ({ page }) => {
  await signIn(page);

  await page.goto('/invoices/create');
  await page.getByLabel('Patient').selectOption({ index: 1 });
  await page.getByLabel('Service 1').selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Save draft' }).click();
  await page.getByRole('button', { name: 'Issue invoice' }).click();
  await page.getByLabel('Amount (INR)').first().fill('200.00');
  await page.getByLabel('Mode').selectOption('CASH');
  await page.getByRole('button', { name: 'Record payment' }).click();
  await page.getByLabel('Refund amount').fill('50.00');
  await page.getByLabel('Refund reason').fill('Browser acceptance refund');
  await page.getByRole('button', { name: 'Refund' }).click();
  await expect(page.getByRole('status')).toContainText('Refund recorded');
  await page.getByLabel('Type').selectOption('CREDIT');
  await page.getByLabel('Amount (INR)').last().fill('25.00');
  await page.getByLabel('Reason', { exact: true }).fill('Browser acceptance credit');
  await page.getByRole('button', { name: 'Record', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Adjustment recorded');
  await expect(page.getByText('Browser acceptance refund')).toBeVisible();
  await expect(page.getByText('Browser acceptance credit')).toBeVisible();

  await page.goto('/invoices/create');
  await page.getByLabel('Patient').selectOption({ index: 1 });
  await page.getByLabel('Service 1').selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Save draft' }).click();
  await page.getByRole('button', { name: 'Issue invoice' }).click();
  await page.getByLabel('Void reason').fill('Browser acceptance void');
  await page.getByRole('button', { name: 'Void invoice' }).click();
  await expect(page.getByRole('status')).toContainText('Invoice voided');
  await expect(page.locator('main')).toContainText('VOID');
});
