import { test, expect } from '@playwright/test';

async function signIn(page, email = 'reception@lotus.test') {
  await page.goto('/login');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel(/^Password/).fill('CareDesk@2026!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function fictionalPatient(page) {
  const response = await page.request.get('/api/v1/patients', { params: { q: 'Demo Patient' } });
  expect(response.status()).toBe(200);
  const patients = await response.json();
  const patient = patients.data.find(candidate => candidate.email === 'demo.patient@lotus.test');
  expect(patient, 'The existing fictional patient seed is required; browser checks never reset application data.').toBeTruthy();
  return patient;
}

test('receptionist searches patient identity, edits demographics and retains the UHID', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  await signIn(page);
  const patient = await fictionalPatient(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Patients', exact: true }).click();
  for (const query of [patient.uhid, `${patient.first_name} ${patient.last_name}`, '90000 00000']) {
    await page.getByLabel('Search patients').fill(query);
    await page.getByRole('button', { name: 'Filter', exact: true }).click();
    await expect(page.getByRole('row').filter({ hasText: patient.uhid })).toBeVisible();
  }
  await page.getByRole('row').filter({ hasText: patient.uhid }).getByRole('link', { name: /^View / }).click();
  await expect(page.getByRole('heading', { name: 'Patient details', exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Edit patient', exact: true }).click();
  const originalCity = await page.getByLabel('City', { exact: true }).inputValue();
  const originalBirthDate = await page.getByLabel('Date of birth', { exact: true }).inputValue();
  await page.getByLabel('Date of birth unknown', { exact: true }).check();
  await expect(page.getByLabel('Date of birth', { exact: true })).toBeDisabled();
  await page.getByLabel('Date of birth unknown', { exact: true }).uncheck();
  await expect(page.getByLabel('Date of birth', { exact: true })).toHaveValue(originalBirthDate);
  try {
    await page.getByLabel('City', { exact: true }).fill('Browser verification city');
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/patients/${patient.id}$`));
    await expect(page.getByRole('status')).toContainText('Patient updated');
    await expect(page.locator('main')).toContainText(patient.uhid);
    await expect(page.locator('main')).toContainText('Browser verification city');
    await page.screenshot({ path: 'test-results/patient-details-desktop.png', fullPage: true });
  } finally {
    await page.goto(`/patients/${patient.id}/edit`);
    await page.getByLabel('City', { exact: true }).fill(originalCity);
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/patients/${patient.id}$`));
    await expect(page.getByRole('status')).toContainText('Patient updated');
  }
  expect(pageErrors).toEqual([]);
});

test.describe('registration without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('possible duplicates preserve the form and require an explicit separate registration choice', async ({ page }) => {
    await signIn(page);
    const patient = await fictionalPatient(page);
    const before = await (await page.request.get('/api/v1/patients')).json();
    await page.goto('/patients/create');
    await page.getByLabel('First name', { exact: false }).fill(patient.first_name);
    await page.getByLabel('Last name', { exact: true }).fill(patient.last_name || '');
    await page.getByLabel('Date of birth', { exact: true }).fill(patient.date_of_birth.slice(0, 10));
    await page.getByLabel('Gender', { exact: false }).selectOption(patient.gender);
    await page.getByLabel('Email address', { exact: true }).fill(patient.email);
    await page.getByRole('button', { name: 'Register patient', exact: true }).click({ force: true });
    await expect(page.getByRole('heading', { name: 'Review possible duplicates', exact: true })).toBeVisible();
    await expect(page.getByLabel('First name', { exact: false })).toHaveValue(patient.first_name);
    await expect(page.getByLabel('Email address', { exact: true })).toHaveValue(patient.email);
    await expect(page.getByLabel('I reviewed these records and want to register a separate patient.')).not.toBeChecked();
    const candidate = page.getByRole('listitem').filter({ hasText: patient.uhid });
    await expect(candidate.getByRole('link')).toHaveAttribute('href', new RegExp(`/patients/${patient.id}$`));
    await expect(candidate).toContainText(patient.email);
    await page.getByRole('button', { name: 'Register patient', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Review possible duplicates', exact: true })).toBeVisible();
    const after = await (await page.request.get('/api/v1/patients')).json();
    expect(before.total).toEqual(expect.any(Number));
    expect(after.total).toBe(before.total);
    await page.screenshot({ path: 'test-results/patient-duplicate-review.png', fullPage: true });
  });
});

test('doctor can find and view patients but cannot register or edit them', async ({ page }) => {
  await signIn(page, 'doctor@lotus.test');
  const patient = await fictionalPatient(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Patients', exact: true }).click();
  await expect(page.getByRole('link', { name: 'Register patient', exact: true })).toHaveCount(0);
  await page.getByRole('row').filter({ hasText: patient.uhid }).getByRole('link', { name: /^View / }).click();
  await expect(page.getByRole('heading', { name: 'Patient details', exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Edit patient', exact: true })).toHaveCount(0);
  await page.getByRole('link', { name: 'Clinical history' }).click();
  await expect(page.getByRole('heading', { name: 'Clinical history', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Add allergy', exact: true })).toBeVisible();
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Doctors', exact: true }).click();
  await page.getByRole('link', { name: 'Availability', exact: true }).first().click();
  await expect(page.getByRole('heading', { name: 'Doctor availability', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Add weekly hours', exact: true })).toBeVisible();
  await expect(page.locator('main')).toContainText('Asia/Kolkata');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Appointments', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Appointments', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Book appointment', exact: true })).toHaveCount(0);
  for (const path of ['/patients/create', `/patients/${patient.id}/edit`]) {
    expect((await page.goto(path)).status()).toBe(403);
  }
});

test('receptionist opens the appointment booking workspace', async ({ page }) => {
  await signIn(page);
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Appointments', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Appointments', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Book appointment', exact: true })).toBeVisible();
  await expect(page.locator('#patient_id')).toBeVisible();
  await expect(page.locator('#doctor_profile_id')).toBeVisible();
  const queue = await (await page.request.get('/api/v1/appointments')).json();
  expect(queue.data.length).toBeGreaterThan(0);
  await page.goto(`/appointments?date=${queue.data[0].appointment_date.slice(0, 10)}`);
  await expect(page.getByRole('heading', { name: /Daily queue/ })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Check in', exact: true }).first()).toBeVisible();
  await expect(page.getByLabel('Doctor filter', { exact: true })).toBeVisible();
  await expect(page.getByLabel('Department', { exact: true })).toBeVisible();
});

test('mobile registration supports unknown birth dates and shows invalid contact errors', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await signIn(page);
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Patients', exact: true }).click();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.getByRole('link', { name: 'Register patient', exact: true }).first().click();
  await page.getByLabel('First name', { exact: false }).fill('Browser draft');
  await page.getByLabel('Date of birth unknown', { exact: true }).check();
  await expect(page.getByLabel('Date of birth', { exact: true })).toBeDisabled();
  await page.getByLabel('Gender', { exact: false }).selectOption('unknown');
  await page.getByLabel('Mobile number', { exact: true }).fill('12');
  await page.getByRole('button', { name: 'Register patient', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Please check the highlighted fields.');
  await expect(page.getByLabel('Mobile number', { exact: true })).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByLabel('Date of birth unknown', { exact: true })).toBeChecked();
  await expect(page.getByLabel('Date of birth', { exact: true })).toBeDisabled();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.screenshot({ path: 'test-results/patient-registration-mobile.png', fullPage: true });
});
