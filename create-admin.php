
<?php
require __DIR__ . '/config/bootstrap.php';

// Only allow setup if no administrator exists
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");

if ((int)$stmt->fetchColumn() > 0) {
    exit('An administrator already exists. Delete this setup file.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter a valid name and email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (?, ?, ?, 'admin')"
        );

        try {
            $stmt->execute([$name, $email, $hash]);
            exit('Administrator created successfully. Delete create-admin.php and log in.');
        } catch (PDOException $e) {
            $message = 'Could not create administrator. Check whether the email already exists.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Administrator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen flex items-center justify-center p-4">

<div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
    <h1 class="text-2xl font-bold text-blue-800 mb-5">
        Create Administrator
    </h1>

    <?php if ($message): ?>
        <p class="text-red-600 mb-4">
            <?= e($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <input type="hidden" name="csrf"
               value="<?= e(csrf_token()) ?>">

        <input name="full_name" required
               placeholder="Full Name"
               class="w-full border rounded-lg p-3">

        <input type="email" name="email" required
               placeholder="Email Address"
               class="w-full border rounded-lg p-3">

        <input type="password" name="password" required
               minlength="8" placeholder="Password (minimum 8 characters)"
               class="w-full border rounded-lg p-3">

        <button type="submit"
                class="w-full bg-blue-700 text-white p-3 rounded-lg">
            Create Administrator
        </button>

    </form>
</div>

</body>
</html>
