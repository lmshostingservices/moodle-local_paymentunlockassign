# Payment Unlock Assignment (local_paymentunlockassign)

Charge students a fee to reopen a Moodle assignment after its deadline, paid by card through Stripe.

When a student misses an assignment's cut-off date, they see a "Reopen Assignment" button on the assignment page. They pay through Stripe Checkout, and the assignment reopens for them for a set number of days. Everyone else's deadlines are untouched.

Built by LMS Labs (LMS Hosting Services) for Australian VET/RTO providers. The plugin does not use AI.

## Requirements

- Moodle 4.4 or later. Tested on Moodle 4.5 and 5.1.
- PHP 8.1 or later.
- A Stripe account. Use test-mode keys on staging and live keys in production.
- An LMS Labs Site ID and API key, with the plugin unlocked on lms-labs.com.

## Install

1. Go to *Site administration > Plugins > Install plugins* and upload the ZIP. If you install it by hand, unzip it into `local/` on Moodle 4.x or `public/local/` on Moodle 5.x, and name the folder `paymentunlockassign`.
2. Complete the upgrade.
3. Open *Site administration > Plugins > Local plugins > Payment Unlock Assignment*.

## Set up

1. **LMS Labs credentials.** Enter your Site ID and API key. If AI Grader Central Config (`local_aiconfig`) is installed, its credentials are used instead.
2. **Stripe keys.** Enter the secret key (`sk_test_…` or `sk_live_…`).
3. **Stripe webhook.** In the Stripe Dashboard, open *Developers > Webhooks* and add an endpoint:
   - URL: `https://<your-moodle>/local/paymentunlockassign/webhook.php`
   - Event: `checkout.session.completed`

   Copy the endpoint's signing secret (`whsec_…`) into the plugin settings.
4. **Defaults.** Set the default currency, fees for attempts 1 and 2, the attempt-3 policy (blocked, or admin approval) and the reopen duration in days.
5. **Turn it on per assignment.** Open *Manage Payment Unlock* and choose Edit next to an assignment, then tick Enabled. Each assignment can have its own fees, lock date source (cut-off date or due date), custom lock date and passing-grade setting.

## How it works

- **Who sees a paywall.** A student sees the paywall only when:
  - the plugin is enabled site-wide and on that assignment;
  - the student's effective lock date has passed;
  - they have no Moodle extension that is still open;
  - their latest attempt is not submitted and awaiting marking;
  - they have not already passed, if *Hide button for students who have passed* is on.
- **Effective dates** are each student's own due and cut-off dates: a user override first, then a group override, then the assignment's dates, then any extension granted in Moodle.
- **Paying.** When Stripe confirms payment, the student gets a personal override covering the reopen period, and the payment is recorded with an invoice reference.
- **Never affected:** teachers and markers, and any assignment where the plugin is not enabled.
- **Pausing.** Untick *Enable Payment Unlock (Global)* in the plugin settings to pause the plugin everywhere. It leaves nothing behind in Moodle's assignment data.

## Admin tools (Manage Payment Unlock page)

- **Assignments:** turn the plugin on per assignment and set its fees and rules.
- **Student Access:** reopen, extend, lock or approve an individual student. *Lock* only removes an override the plugin created; overrides set by teachers are kept.
- **Payments:** successful payments and revenue by currency.
- **Audit Log:** every unlock, override and checkout event.

## Before going live

- Run one Stripe test-mode payment end to end on staging.
- On sites that ran v1.2.6 or earlier, check for personal overrides left behind by the old free-window feature before activating.
- Turn the plugin on for one assignment first.

## Privacy

The plugin stores the student's access status, payment records (amount, currency, Stripe session ID, invoice reference) and an audit log. No card details are stored; Stripe handles them. The privacy provider exports and deletes this data through Moodle's privacy tools.

## Support

lms-labs.com

## Licence

GNU GPL v3 or later. See `LICENSE`.
