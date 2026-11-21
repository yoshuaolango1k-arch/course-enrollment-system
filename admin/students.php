<?php
require __DIR__.'/../config/bootstrap.php';
require_role('admin');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    if ($action==='delete') {
        try { $d=$pdo->prepare("DELETE FROM users WHERE id=? AND role='student'"); $d->execute([$id]); flash('Student deleted.'); }
        catch(PDOException $e) { flash('Student has payment or enrollment records and cannot be deleted.','error'); }
        redirect('admin/students.php');
    }
    if ($action==='edit') {
        $name=trim($_POST['full_name']??''); $email=strtolower(trim($_POST['email']??'')); $phone=trim($_POST['phone']??''); $password=$_POST['password']??'';
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Enter a valid student name and email.';
        else {
            try {
                if ($password!=='') {
                    if (strlen($password)<8) throw new InvalidArgumentException('Password must contain at least 8 characters.');
                    $q=$pdo->prepare("UPDATE users SET full_name=?,email=?,phone=?,password_hash=? WHERE id=? AND role='student'");
                    $q->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT),$id]);
                } else {
                    $q=$pdo->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=? AND role='student'");
                    $q->execute([$name,$email,$phone,$id]);
                }
                flash('Student details updated.'); redirect('admin/students.php');
            } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
              catch (PDOException $e) { $error='Could not save. Check whether the email is already in use.'; }
        }
    }
}
$edit=null;
if(isset($_GET['edit'])) { $q=$pdo->prepare("SELECT id,full_name,email,phone FROM users WHERE id=? AND role='student'"); $q->execute([(int)$_GET['edit']]); $edit=$q->fetch(); }
$students=$pdo->query("SELECT u.id,u.full_name,u.email,u.phone,u.created_at,COUNT(DISTINCT e.id) AS enrolled FROM users u LEFT JOIN enrollments e ON e.user_id=u.id AND e.status='active' WHERE u.role='student' GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();
$page_title='Manage students'; require __DIR__.'/../includes/header.php'; ?>
<h1 class="text-3xl font-bold">Manage students</h1>
<?php if($error): ?><p class="mt-4 text-red-700"><?= e($error) ?></p><?php endif; ?>
<?php if($edit): ?><form method="post" class="mt-5 max-w-xl bg-white border rounded-xl p-5 space-y-3"><h2 class="font-bold text-xl">Edit student</h2><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><label class="block">Full name<input required name="full_name" value="<?= e($edit['full_name']) ?>" class="mt-1 w-full border p-3 rounded-lg"></label><label class="block">Email<input required type="email" name="email" value="<?= e($edit['email']) ?>" class="mt-1 w-full border p-3 rounded-lg"></label><label class="block">Phone<input name="phone" value="<?= e($edit['phone']) ?>" class="mt-1 w-full border p-3 rounded-lg"></label><label class="block">New password <span class="text-slate-500">(leave blank to keep current)</span><input type="password" minlength="8" name="password" class="mt-1 w-full border p-3 rounded-lg"></label><div class="flex gap-3"><button class="bg-blue-700 text-white px-5 py-3 rounded-lg">Save changes</button><a href="<?= BASE_URL ?>/admin/students.php" class="border px-5 py-3 rounded-lg">Cancel</a></div></form><?php endif; ?>
<div class="overflow-x-auto bg-white border rounded-xl mt-6"><table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-3 text-left">Name</th><th class="p-3 text-left">Email</th><th class="p-3">Phone</th><th class="p-3">Active courses</th><th class="p-3">Actions</th></tr></thead><tbody><?php foreach($students as $s): ?><tr class="border-t"><td class="p-3"><?= e($s['full_name']) ?></td><td class="p-3"><?= e($s['email']) ?></td><td class="p-3"><?= e($s['phone']) ?></td><td class="p-3 text-center"><?= (int)$s['enrolled'] ?></td><td class="p-3"><div class="flex gap-3 items-center"><a class="text-blue-700" href="?edit=<?= (int)$s['id'] ?>">Edit</a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button data-confirm="Delete this student?" class="text-red-600">Delete</button></form></div></td></tr><?php endforeach; ?><?php if(!$students): ?><tr><td colspan="5" class="p-5 text-center text-slate-500">No students registered yet.</td></tr><?php endif; ?></tbody></table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
