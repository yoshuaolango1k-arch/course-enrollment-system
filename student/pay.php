<?php require __DIR__.'/../config/bootstrap.php';require_role('student');$id=(int)($_GET['course_id']??$_POST['course_id']??0);$s=$pdo->prepare('SELECT * FROM courses WHERE id=? AND is_active=1');$s->execute([$id]);$course=$s->fetch();if(!$course)exit('Course not found.');$q=$pdo->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=? AND status='active'");$q->execute([current_user()['id'],$id]);if($q->fetch()) {flash('You are already enrolled in this course.');redirect('student/dashboard.php');}
$error='';if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();$provider=$_POST['provider']??'';if(!in_array($provider,['paystack','mpesa'],true))$error='Choose a payment method.';else{
    $old=$pdo->prepare("SELECT id,created_at FROM payments WHERE user_id=? AND course_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
    $old->execute([current_user()['id'],$id]); $pending=$old->fetch();
    if($pending && strtotime($pending['created_at']) > time()-1200) {
        $error='A payment for this course is already pending. Complete it or wait 20 minutes before starting another payment.';
    } else {
        if($pending){$expire=$pdo->prepare("UPDATE payments SET status='failed' WHERE id=? AND status='pending'");$expire->execute([$pending['id']]);}
        $ref='CES'.strtoupper(bin2hex(random_bytes(8)));
        $ins=$pdo->prepare("INSERT INTO payments(user_id,course_id,provider,reference,amount_kes,status) VALUES(?,?,?,?,?,'pending')");
        $ins->execute([current_user()['id'],$id,$provider,$ref,ENROLLMENT_FEE]);
        $paymentId=(int)$pdo->lastInsertId(); $_SESSION['pending_payment_id']=$paymentId;
        if($provider==='paystack')redirect('api/paystack-init.php?payment='.$paymentId);else redirect('api/mpesa-init.php?payment='.$paymentId);
    }
}}
$page_title='Course payment';require __DIR__.'/../includes/header.php';?>
<div class="max-w-xl mx-auto bg-white border rounded-xl p-7"><h1 class="text-2xl font-bold">Enroll in <?= e($course['title']) ?></h1><p class="mt-3 text-slate-600">Amount due: <b><?= money(ENROLLMENT_FEE) ?></b></p><?php if($error): ?><p class="text-red-600 mt-3"><?= e($error) ?></p><?php endif; ?><form method="post" class="mt-6 space-y-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="course_id" value="<?= $id ?>"><label class="flex gap-3 border p-4 rounded-lg"><input type="radio" name="provider" value="paystack" required><span><b>Paystack</b><small class="block text-slate-500">Pay using the Paystack test checkout.</small></span></label><label class="flex gap-3 border p-4 rounded-lg"><input type="radio" name="provider" value="mpesa" required><span><b>M-Pesa</b><small class="block text-slate-500">Receive an STK Push prompt on your phone.</small></span></label><button class="w-full bg-blue-700 text-white p-3 rounded-lg">Continue to payment</button></form></div><?php require __DIR__.'/../includes/footer.php';?>
