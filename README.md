# Course Enrollment System

A student-level PHP/MySQL course enrollment application with Student and Administrator roles, KSh 1 enrollment payments, Paystack checkout and M-Pesa Daraja STK Push integration points.

## Requirements
- PHP 8.1+ with cURL and PDO MySQL extensions
- MySQL / MariaDB
- XAMPP or equivalent
- Paystack test keys and Safaricom Daraja sandbox credentials for payment testing
- ngrok for exposing the local callback URL during M-Pesa testing

## Setup (XAMPP on Windows)
1. Extract this folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create/import the database by selecting **Import** and choosing `database.sql`. If you already imported an earlier copy, run `migration_add_provider_receipt.sql` once in phpMyAdmin instead of importing the database again.
5. Copy `.env.example` to `.env`.
6. Edit `.env` with your local database settings and payment sandbox credentials.
7. Open `http://localhost/course-enrollment-system/`.
8. Register a student account.
9. Create the first administrator using the one-time setup script described below.

## Create an administrator
For safety, there is no public admin registration. Temporarily open `create-admin.php` in your browser:
`http://localhost/course-enrollment-system/create-admin.php`
Enter a name, email and password. After successful creation, DELETE `create-admin.php` from the project folder.

## Payment configuration
### Paystack
Set `PAYSTACK_SECRET_KEY` in `.env` to your Paystack **test** secret key. Checkout is initiated by the PHP backend. The callback verifies the reference directly with Paystack before an enrollment is activated.

### M-Pesa Daraja sandbox
Set the consumer key/secret, shortcode, passkey and callback URL in `.env`. Run ngrok against Apache, for example:
`ngrok http 80`
Set `MPESA_CALLBACK_URL` to the public HTTPS URL followed by:
`/course-enrollment-system/api/mpesa-callback.php`

The callback URL must be publicly reachable. Use the Daraja sandbox simulator and test credentials. Never use production credentials for development.

## Important configuration
- Never commit `.env`.
- Never put secret keys in JavaScript or HTML.
- The app stores transaction references and checks payment state server-side.
- M-Pesa callbacks are matched to the saved CheckoutRequestID and MerchantRequestID, amount, and receipt number, then processed idempotently. Paystack transactions are verified against the provider API, expected reference, KES currency, amount, and student email. For a real production deployment, also add Safaricom IP/network controls and operational reconciliation.
- Payment integrations require valid provider sandbox credentials and provider-side configuration; placeholder credentials do not make payments work.

## Git
`.gitignore` excludes `.env`, logs, and local secrets. Commit `.env.example`, not `.env`.

## Main features
- Student registration and login
- Student course catalogue and course detail view
- KSh 1 enrollment flow
- Paystack checkout and verification
- M-Pesa STK Push and callback handling
- Student dashboard and enrolled course access
- Admin dashboard, student management, course CRUD, enrollments and transactions
- PDO prepared statements, password hashing, sessions and role checks

## Testing checklist
- Register and log in as a student.
- Create an administrator and log in.
- Add a course in the admin dashboard.
- Start a payment with each sandbox provider.
- Confirm enrollment only becomes active after verified success.
- Test a failed/cancelled payment.
- Confirm re-sending the same callback does not create duplicate processing.

## Notes before submission
- Student records can be edited by an administrator; a new password is optional during editing.
- Course deletion is implemented as archiving so historical enrollments and payment records remain intact.
- M-Pesa callback processing requires a reachable HTTPS ngrok URL and valid Daraja sandbox credentials. Do not mark a transaction successful manually.
- Delete `create-admin.php` immediately after creating the first administrator.
