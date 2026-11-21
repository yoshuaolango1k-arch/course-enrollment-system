
<?php

require __DIR__ . '/config/bootstrap.php';

if (current_user()) {
    redirect(
        current_user()['role'] === 'admin'
            ? 'admin/dashboard.php'
            : 'student/dashboard.php'
    );
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Retrieve the student's phone number together with login details
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            password_hash,
            role
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        session_regenerate_id(true);

        // Remove password hash before saving user in session
        unset($user['password_hash']);

        // Save complete user information, including phone
        $_SESSION['user'] = $user;

        // Redirect according to user role
        if ($user['role'] === 'admin') {
            redirect('admin/dashboard.php');
        } else {
            redirect('student/dashboard.php');
        }

    } else {
        $error = 'Incorrect email or password.';
    }
}

$page_title = 'Login';

require __DIR__ . '/includes/header.php';

?>

<div class="max-w-md mx-auto bg-white p-7 rounded-xl border shadow-sm">

    <h1 class="text-2xl font-bold">Sign in</h1>

    <?php if ($error): ?>
        <p class="text-red-600 mt-3">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <form method="post" class="space-y-4 mt-5">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf_token()) ?>"
        >

        <label class="block">
            Email

            <input
                required
                type="email"
                name="email"
                class="mt-1 w-full border rounded-lg p-3"
            >
        </label>

        <label class="block">
            Password

            <input
                required
                type="password"
                name="password"
                class="mt-1 w-full border rounded-lg p-3"
            >
        </label>

        <button
            type="submit"
            class="w-full bg-blue-700 text-white p-3 rounded-lg"
        >
            Login
        </button>

    </form>

    <p class="mt-4 text-sm">
        New student?

        <a
            class="text-blue-700 underline"
            href="<?= BASE_URL ?>/register.php"
        >
            Register here
        </a>
    </p>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
